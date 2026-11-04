<?php

namespace App\WhatsApp\Labels;

use App\Leads\Actions\RecordActivity;
use App\Leads\Actions\SetFollowUp;
use App\Leads\ActivityEvent;
use App\Leads\Lead;
use App\Leads\Services\SalesStageTransitionService;
use App\Models\User;
use App\SalesPipelines\PipelineStage;
use App\SalesPipelines\StageKind;
use App\Security\Role;
use App\Settings\DisplayClock;
use App\WhatsApp\Services\InboundConversationResolver;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Keeps WhatsApp Business labels in step with the desk: the label list, which
 * contacts carry which labels, and what a label means for the lead.
 */
class LabelSync
{
    public function __construct(
        private InboundConversationResolver $resolver,
        private SalesStageTransitionService $transitions,
        private SetFollowUp $followUps,
        private RecordActivity $activity,
        private DisplayClock $clock,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function upsertLabel(array $data): ?WhatsAppLabel
    {
        $id = (string) ($data['id'] ?? $data['labelId'] ?? '');
        $name = trim((string) ($data['name'] ?? ''));

        if ($id === '' || $name === '') {
            return null;
        }

        return WhatsAppLabel::query()->updateOrCreate(
            ['label_id' => $id],
            [
                'name' => mb_substr($name, 0, 120),
                'color' => isset($data['color']) ? (string) $data['color'] : null,
                'deleted' => ($data['deleted'] ?? false) === true,
            ],
        );
    }

    /**
     * @return string one of "linked", "unlinked", "unknown-chat", "ignored"
     */
    public function associate(string $chatJid, string $labelId, bool $add): string
    {
        $chat = $this->chatId($chatJid);

        if ($chat === null || $labelId === '') {
            return 'ignored';
        }

        $lead = $this->resolver->existing($chat);

        if ($lead === null) {
            return 'unknown-chat';
        }

        $contactId = $lead->contact_id;
        $exists = DB::table('contact_whatsapp_label')->where('contact_id', $contactId)->where('label_id', $labelId)->exists();

        if ($add && ! $exists) {
            DB::table('contact_whatsapp_label')->insert([
                'contact_id' => $contactId,
                'label_id' => $labelId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } elseif (! $add && $exists) {
            DB::table('contact_whatsapp_label')->where('contact_id', $contactId)->where('label_id', $labelId)->delete();
        } else {
            return $add ? 'linked' : 'unlinked';
        }

        $label = WhatsAppLabel::query()->where('label_id', $labelId)->first();
        $name = $label->name ?? 'label '.$labelId;

        $this->activity->handle(ActivityEvent::WhatsAppLabelChanged, $lead, $lead->contact, null, [
            'label' => ($add ? 'WhatsApp label added: ' : 'WhatsApp label removed: ').$name,
            'label_id' => $labelId,
        ]);

        if ($add && $label !== null) {
            $this->apply($lead, $label);
        }

        return $add ? 'linked' : 'unlinked';
    }

    /**
     * Re-applies the rules for every labelled contact (after label names
     * arrive later than the associations).
     */
    public function reapply(): int
    {
        $count = 0;

        foreach (DB::table('contact_whatsapp_label')->get() as $row) {
            $label = WhatsAppLabel::query()->where('label_id', $row->label_id)->first();
            $lead = Lead::query()->where('contact_id', $row->contact_id)->orderByRaw('closed_at is not null')->orderByDesc('updated_at')->first();

            if ($label !== null && $lead !== null && $this->apply($lead, $label)) {
                $count++;
            }
        }

        return $count;
    }

    public function actionFor(WhatsAppLabel $label): ?string
    {
        $name = mb_strtolower($label->name);

        foreach ((array) config('whatsapp.label_rules', []) as $rule) {
            foreach ((array) ($rule['match'] ?? []) as $word) {
                if (str_contains($name, mb_strtolower((string) $word))) {
                    return (string) $rule['action'];
                }
            }
        }

        return null;
    }

    private function apply(Lead $lead, WhatsAppLabel $label): bool
    {
        if ($label->deleted || $lead->closed_at !== null) {
            return false;
        }

        $actor = User::query()->where('role', Role::Owner->value)->orderBy('id')->first()
            ?? User::query()->orderBy('id')->first();

        if ($actor === null) {
            return false;
        }

        $action = $this->actionFor($label);

        try {
            if ($action === 'follow_up') {
                $today = $this->clock->today();

                if ($lead->next_follow_up_on === null || $lead->next_follow_up_on->toDateString() < $today) {
                    $this->followUps->handle($lead, $lead->next_action ?: 'Follow up (WhatsApp label "'.$label->name.'")', $today, $actor, 'whatsapp_label');

                    return true;
                }

                return false;
            }

            if ($action === 'won' || $action === 'lost') {
                $kind = $action === 'won' ? StageKind::Won : StageKind::Lost;
                $target = $lead->currentStage->outgoingTransitions()
                    ->with('toStage')
                    ->get()
                    ->map(fn ($transition) => $transition->toStage)
                    ->first(fn (?PipelineStage $stage) => $stage !== null && $stage->archived_at === null && $stage->kind === $kind);

                if ($target === null) {
                    return false;
                }

                $this->transitions->transition($lead, $target, $actor, 'WhatsApp label: '.$label->name);

                return true;
            }
        } catch (Throwable $exception) {
            report($exception);
        }

        return false;
    }

    private function chatId(string $jid): ?string
    {
        if ($jid === '' || str_ends_with($jid, '@g.us') || str_starts_with($jid, 'status@')) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', explode('@', $jid)[0]) ?? '';

        if ($digits === '') {
            return null;
        }

        return str_ends_with($jid, '@lid') ? '+'.$digits : $digits;
    }
}
