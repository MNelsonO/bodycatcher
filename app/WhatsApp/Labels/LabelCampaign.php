<?php

namespace App\WhatsApp\Labels;

use App\Ai\LlmClient;
use App\Ai\LlmConfiguration;
use App\Ai\SalesPlaybook;
use App\Ai\Services\LeadConversationMemory;
use App\Contacts\ConsentStatus;
use App\Conversations\Message;
use App\FollowUps\FollowUpAction;
use App\FollowUps\FollowUpTask;
use App\FollowUps\FollowUpTaskStatus;
use App\Leads\Actions\RecordActivity;
use App\Leads\ActivityEvent;
use App\Leads\Lead;
use App\Models\User;
use App\Settings\DisplayClock;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Messaging everyone with one WhatsApp label: who is safe to message, a
 * personal draft for each, and sends spaced out so the number stays safe.
 */
class LabelCampaign
{
    public function __construct(
        private LlmClient $llm,
        private LlmConfiguration $llmConfig,
        private SalesPlaybook $playbook,
        private LeadConversationMemory $memory,
        private DisplayClock $clock,
        private RecordActivity $activity,
    ) {}

    /**
     * @return array<int, array{label_id: string, name: string, color: string, attached: int, waiting: int}>
     */
    public function labels(): array
    {
        $attached = DB::table('contact_whatsapp_label')->selectRaw('label_id, count(*) c')->groupBy('label_id')->pluck('c', 'label_id');
        $waiting = DB::table('whatsapp_pending_labels')->selectRaw('label_id, count(*) c')->groupBy('label_id')->pluck('c', 'label_id');

        return WhatsAppLabel::query()
            ->where('deleted', false)
            ->get()
            ->map(fn (WhatsAppLabel $label): array => [
                'label_id' => $label->label_id,
                'name' => $label->name,
                'color' => $label->hex(),
                'attached' => (int) ($attached[$label->label_id] ?? 0),
                'waiting' => (int) ($waiting[$label->label_id] ?? 0),
            ])
            ->sortByDesc(fn (array $row) => $row['attached'] * 1000 + $row['waiting'])
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, array{lead_id: int, conversation_id: ?int, contact_name: string, stage: string, last_message: ?string, last_message_label: ?string, skip_reason: ?string}>
     */
    public function members(string $labelId): Collection
    {
        $contactIds = DB::table('contact_whatsapp_label')->where('label_id', $labelId)->pluck('contact_id');

        return Lead::query()
            ->with(['contact', 'currentStage', 'conversation.latestMessage', 'product'])
            ->whereIn('contact_id', $contactIds)
            ->orderByRaw('closed_at is not null')
            ->orderByDesc('updated_at')
            ->get()
            ->unique('contact_id')
            ->map(fn (Lead $lead): array => [
                'lead_id' => $lead->id,
                'conversation_id' => $lead->conversation?->id,
                'contact_name' => $lead->contact->full_name,
                'stage' => $lead->currentStage->name,
                'last_message' => $lead->conversation?->latestMessage?->body,
                'last_message_label' => $this->clock->formatDateTime($lead->conversation?->last_message_at),
                'skip_reason' => $this->skipReason($lead),
            ])
            ->values();
    }

    public function skipReason(Lead $lead): ?string
    {
        if ($lead->contact->consent_status === ConsentStatus::OptedOut) {
            return 'Opted out';
        }

        if ($lead->conversations_stopped) {
            return 'Conversations stopped';
        }

        if ($lead->closed_at !== null) {
            return 'Lead closed';
        }

        $conversation = $lead->conversation;

        if ($conversation === null || ! Message::query()->where('conversation_id', $conversation->id)->where('direction', 'inbound')->where('body', '!=', '')->exists()) {
            return 'Never wrote to you';
        }

        $quiet = (int) config('whatsapp.safety.campaign_quiet_hours', 12);

        if (Message::query()->where('conversation_id', $conversation->id)->where('direction', 'outbound')->where('sent_at', '>', now()->subHours($quiet))->exists()) {
            return "Messaged in the last {$quiet}h";
        }

        if (FollowUpTask::query()->where('lead_id', $lead->id)->where('status', FollowUpTaskStatus::Pending)->where('action', FollowUpAction::SendWhatsApp)->exists()) {
            return 'Already has a message scheduled';
        }

        return null;
    }

    /**
     * @return array<int, array{lead_id: int, conversation_id: ?int, contact_name: string, body: ?string, skip_reason: ?string, ai: bool}>
     */
    public function drafts(string $labelId, string $goal, string $template): array
    {
        $limit = (int) config('whatsapp.safety.campaign_per_day', 30);
        $rows = [];

        foreach ($this->members($labelId) as $member) {
            $lead = Lead::query()->with(['contact', 'product', 'currentStage', 'conversation'])->whereKey($member['lead_id'])->first();

            if ($lead === null) {
                continue;
            }

            if ($member['skip_reason'] !== null || count(array_filter($rows, fn ($r) => $r['body'] !== null)) >= $limit) {
                $rows[] = [...$this->rowBase($member), 'body' => null, 'skip_reason' => $member['skip_reason'] ?? "Over today's limit of {$limit}", 'ai' => false];

                continue;
            }

            [$body, $ai] = $this->draftFor($lead, $goal, $template);

            $rows[] = [...$this->rowBase($member), 'body' => $body, 'skip_reason' => $body === null ? 'Could not write a short, safe message' : null, 'ai' => $ai];
        }

        return $rows;
    }

    /**
     * @param  array<int, array{lead_id: int, body: string}>  $messages
     * @return array{scheduled: int, skipped: int, first_at: ?string, last_at: ?string}
     */
    public function schedule(array $messages, User $actor): array
    {
        $gap = max(60, (int) config('whatsapp.safety.campaign_gap_seconds', 150));
        $limit = (int) config('whatsapp.safety.campaign_per_day', 30);
        $at = now()->addMinutes(2);
        $result = ['scheduled' => 0, 'skipped' => 0, 'first_at' => null, 'last_at' => null];
        $batch = 'campaign:'.Str::uuid()->toString();

        foreach ($messages as $row) {
            $lead = Lead::query()->with(['contact', 'conversation'])->find($row['lead_id']);
            $body = trim($row['body']);

            if ($lead === null || $body === '' || $this->skipReason($lead) !== null || $result['scheduled'] >= $limit || $this->playbook->fitForWhatsApp($body) === null) {
                $result['skipped']++;

                continue;
            }

            $task = FollowUpTask::query()->create([
                'lead_id' => $lead->id,
                'automation_rule_id' => null,
                'scheduled_by_user_id' => $actor->id,
                'idempotency_key' => $batch.':'.$lead->id,
                'action' => FollowUpAction::SendWhatsApp,
                'body' => $body,
                'due_at' => $at,
                'anchor_at' => null,
                'cancel_on_reply' => true,
                'status' => FollowUpTaskStatus::Pending,
            ]);

            $this->activity->handle(ActivityEvent::MessageScheduled, $lead, $lead->contact, $actor, [
                'task_id' => $task->id,
                'due' => $this->clock->formatDateTime($at),
                'source' => 'label',
            ]);

            $result['scheduled']++;
            $result['first_at'] ??= $this->clock->formatDateTime($at);
            $result['last_at'] = $this->clock->formatDateTime($at);
            $at = $at->addSeconds($gap + random_int(0, 60));
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $member
     * @return array{lead_id: int, conversation_id: ?int, contact_name: string}
     */
    private function rowBase(array $member): array
    {
        return [
            'lead_id' => (int) $member['lead_id'],
            'conversation_id' => $member['conversation_id'],
            'contact_name' => (string) $member['contact_name'],
        ];
    }

    /**
     * @return array{0: ?string, 1: bool}
     */
    private function draftFor(Lead $lead, string $goal, string $template): array
    {
        if ($this->llmConfig->ready() && trim($goal) !== '') {
            try {
                $reply = $this->llm->complete(
                    $this->playbook->block($lead)."\n\nWrite ONE WhatsApp message to this person to achieve the desk's goal, continuing naturally from their chat. Plain text only, 1-3 short sentences. Reply with the message only.",
                    "Desk goal: {$goal}\n\n".$this->memory->asPromptBlock($lead, 20),
                );
                $fit = $this->playbook->fitForWhatsApp(trim($reply, " \n\"'"));

                if ($fit !== null) {
                    return [$fit, true];
                }
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        if (trim($template) === '') {
            return [null, false];
        }

        $name = trim((string) $lead->contact->full_name);
        $first = preg_match('/^\+?[\d\s]+$/', $name) === 1 || str_starts_with($name, 'WhatsApp') ? '' : explode(' ', $name)[0];
        $link = $this->playbook->productFor($lead->product)['url'] ?? (string) config('kemet.company.website', 'https://kemettech.com');

        $body = strtr($template, [
            '{name}' => $first,
            '{business}' => (string) ($lead->contact->business_name ?? ''),
            '{link}' => $link,
        ]);
        $body = trim(preg_replace('/\s+([,!.?])/', '$1', preg_replace('/ {2,}/', ' ', $body) ?? $body) ?? $body);

        return [$this->playbook->fitForWhatsApp($body), false];
    }
}
