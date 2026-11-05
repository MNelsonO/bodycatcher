<?php

namespace App\Conversations\Services;

use App\Calendar\CalendarActivity;
use App\Conversations\Conversation;
use App\Conversations\Message;
use App\Conversations\MessageAttachment;
use App\Conversations\MessageDirection;
use App\FollowUps\FollowUpAction;
use App\FollowUps\FollowUpTask;
use App\FollowUps\FollowUpTaskStatus;
use App\Leads\Interest\InterestClassifier;
use App\Leads\Lead;
use App\Leads\LeadPageData;
use App\Settings\DisplayClock;
use App\WhatsApp\Labels\WhatsAppLabel;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InboxData
{
    private const THREAD_LIMIT = 300;

    public function __construct(
        private DisplayClock $clock,
        private LeadPageData $leadPage,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function conversations(Request $request): array
    {
        $search = trim($request->string('q')->toString());
        $filter = $request->string('filter')->toString();

        $query = Conversation::query()
            ->select('conversations.*')
            ->withUnreadCount()
            ->with(['contact', 'lead.currentStage', 'lead.product', 'latestMessage'])
            ->whereHas('messages');

        if ($search !== '') {
            $digits = preg_replace('/\D+/', '', $search) ?? '';
            $local = str_starts_with($digits, '0') ? '254'.substr($digits, 1) : $digits;

            $query->whereHas('contact', function (Builder $contact) use ($search, $digits, $local): void {
                $contact->where(function (Builder $inner) use ($search, $digits, $local): void {
                    $like = '%'.mb_strtolower($search).'%';
                    $inner->whereRaw('lower(full_name) like ?', [$like])
                        ->orWhereRaw('lower(coalesce(business_name, \'\')) like ?', [$like]);

                    if (strlen($digits) >= 3) {
                        $inner->orWhere('primary_phone', 'like', '%'.$local.'%');
                    }
                });
            });
        }

        $label = $request->string('label')->toString();

        if ($label !== '') {
            $query->whereIn('conversations.contact_id', DB::table('contact_whatsapp_label')->where('label_id', $label)->select('contact_id'));
        }

        if ($filter === 'unread') {
            $query->whereExists(function ($exists): void {
                $exists->from('messages')
                    ->whereColumn('messages.conversation_id', 'conversations.id')
                    ->where('messages.direction', MessageDirection::Inbound->value)
                    ->where(function ($inner): void {
                        $inner->whereNull('conversations.last_read_at')
                            ->orWhereColumn('messages.sent_at', '>', 'conversations.last_read_at');
                    });
            });
        } elseif ($filter === 'attention') {
            $query->whereHas('lead', fn (Builder $lead) => $lead->where('needs_personal_attention', true)->orWhere('taken_over', true));
        } elseif ($filter === 'unnamed') {
            $query->whereHas('contact', fn (Builder $contact) => $contact->where(function (Builder $inner): void {
                $inner->where('full_name', 'like', '+%')
                    ->orWhere('full_name', 'like', 'WhatsApp%')
                    ->orWhereColumn('full_name', 'primary_phone');
            }));
        } elseif ($filter === 'open') {
            $query->whereHas('lead', fn (Builder $lead) => $lead->whereNull('closed_at'));
        }

        return $query
            ->orderByRaw('coalesce(conversations.last_message_at, conversations.created_at) desc')
            ->orderByDesc('conversations.id')
            ->limit(80)
            ->get()
            ->map(fn (Conversation $conversation): array => $this->row($conversation))
            ->values()
            ->all();
    }

    public function unreadConversationCount(): int
    {
        return (int) DB::table('messages')
            ->join('conversations', 'conversations.id', '=', 'messages.conversation_id')
            ->where('messages.direction', MessageDirection::Inbound->value)
            ->where('messages.body', '!=', '')
            ->where(function ($inner): void {
                $inner->whereNull('conversations.last_read_at')
                    ->orWhereColumn('messages.sent_at', '>', 'conversations.last_read_at');
            })
            ->distinct()
            ->count('messages.conversation_id');
    }

    /**
     * @return array<string, mixed>
     */
    public function thread(Conversation $conversation): array
    {
        $conversation->load(['contact.leads.currentStage', 'contact.leads.product', 'lead.contact', 'lead.currentStage', 'lead.product', 'lead.pipeline']);
        $lead = $conversation->lead;
        $contact = $conversation->contact;

        $messages = Message::query()
            ->with(['loggedBy', 'attachments'])
            ->where('conversation_id', $conversation->id)
            ->where('body', '!=', '')
            ->orderByDesc('sent_at')
            ->orderByDesc('id')
            ->limit(self::THREAD_LIMIT)
            ->get()
            ->reverse()
            ->values();

        $total = Message::query()->where('conversation_id', $conversation->id)->where('body', '!=', '')->count();

        $scheduled = FollowUpTask::query()
            ->where('lead_id', $lead->id)
            ->where('status', FollowUpTaskStatus::Pending)
            ->where('action', FollowUpAction::SendWhatsApp)
            ->orderBy('due_at')
            ->get()
            ->map(fn (FollowUpTask $task): array => [
                'id' => $task->id,
                'body' => $task->body,
                'due_label' => $this->clock->formatDateTime($task->due_at),
                'held_reason' => $task->held_reason,
                'by_hand' => $task->automation_rule_id === null,
            ])
            ->all();

        $appointments = CalendarActivity::query()
            ->where('lead_id', $lead->id)
            ->whereDate('activity_on', '>=', $this->clock->today())
            ->orderBy('activity_on')
            ->orderBy('start_time')
            ->limit(5)
            ->get()
            ->map(fn (CalendarActivity $activity): array => [
                'id' => $activity->id,
                'title' => $activity->title,
                'kind_label' => ucfirst($activity->activity_type),
                'starts_at_label' => $this->clock->formatDate($activity->activity_on)
                    .($activity->start_time !== null ? ', '.$activity->start_time : ''),
            ])
            ->all();

        return [
            'id' => $conversation->id,
            'hidden_earlier' => max(0, $total - self::THREAD_LIMIT),
            'contact' => [
                'id' => $contact->id,
                'full_name' => $contact->full_name,
                'business_name' => $contact->business_name,
                'primary_phone' => $contact->primary_phone,
                'email' => $contact->email,
                'location' => $contact->location,
                'consent_status' => $contact->consent_status->value,
                'labels' => $this->labelsFor($contact->id),
            ],
            'lead' => [
                'id' => $lead->id,
                'product' => $lead->product->name,
                'pipeline' => $lead->pipeline->name,
                'stage' => $lead->currentStage->name,
                'stage_kind' => $lead->currentStage->kind->value,
                'outcome' => $lead->outcome(),
                'next_action' => $lead->next_action,
                'next_follow_up_label' => $this->clock->formatDate($lead->next_follow_up_on),
                'interest' => $this->interest($lead),
                'ad' => $lead->ad_first_seen_at === null ? null : [
                    'title' => $lead->ad_title,
                    'body' => $lead->ad_body !== null ? mb_strimwidth($lead->ad_body, 0, 220, '…') : null,
                    'url' => $lead->ad_url,
                ],
            ],
            'systems' => collect(app(InterestClassifier::class)->systems())->map(fn ($name, $key) => ['value' => $key, 'label' => $name])->values()->all(),
            'other_leads' => $contact->leads
                ->where('id', '!=', $lead->id)
                ->sortByDesc('updated_at')
                ->values()
                ->map(fn ($other): array => [
                    'id' => $other->id,
                    'product' => $other->product->name,
                    'stage' => $other->currentStage->name,
                    'outcome' => $other->outcome(),
                ])
                ->all(),
            'whatsapp' => $this->leadPage->whatsapp($lead),
            'intelligence' => $this->leadPage->intelligence($lead),
            'scheduled' => $scheduled,
            'appointments' => $appointments,
            'messages' => $messages->map(fn (Message $message): array => [
                'id' => $message->id,
                'direction' => $message->direction->value,
                'channel' => $message->channel->value,
                'origin' => $message->origin->value,
                'status' => $message->status->value,
                'body' => $message->body,
                'sent_at' => $message->sent_at->toIso8601String(),
                'day_label' => $this->dayLabel($message->sent_at),
                'time_label' => $message->sent_at->copy()->timezone($this->clock->timezone())->format('H:i'),
                'logged_by' => $message->loggedBy?->name,
                'refusal_reason' => $message->refusal_reason,
                'attachments' => $message->attachments->map(fn (MessageAttachment $attachment): array => [
                    'id' => $attachment->id,
                    'mime_type' => $attachment->mime_type,
                    'caption' => $attachment->caption,
                ])->all(),
            ])->all(),
        ];
    }

    /**
     * @return array{key: ?string, label: ?string, source: ?string}
     */
    private function interest(Lead $lead): array
    {
        return [
            'key' => $lead->interest_system,
            'label' => app(InterestClassifier::class)->label($lead->interest_system),
            'source' => $lead->interest_source,
        ];
    }

    /**
     * @return list<array{name: string, color: string}>
     */
    public function labelsFor(int $contactId): array
    {
        return array_values(WhatsAppLabel::query()
            ->whereIn('label_id', DB::table('contact_whatsapp_label')->where('contact_id', $contactId)->pluck('label_id'))
            ->where('deleted', false)
            ->orderBy('name')
            ->get()
            ->map(fn (WhatsAppLabel $label): array => ['name' => $label->name, 'color' => $label->hex()])
            ->all());
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Conversation $conversation): array
    {
        $latest = $conversation->latestMessage;
        $contact = $conversation->contact;
        $lead = $conversation->lead;

        return [
            'id' => $conversation->id,
            'lead_id' => $lead->id,
            'contact_name' => $contact->full_name,
            'business_name' => $contact->business_name,
            'phone' => $contact->primary_phone,
            'product' => $lead->product->name,
            'stage' => $lead->currentStage->name,
            'outcome' => $lead->outcome(),
            'needs_attention' => $lead->needs_personal_attention || $lead->taken_over,
            'labels' => $this->labelsFor($contact->id),
            'interest' => $this->interest($lead),
            'opted_out' => $contact->consent_status->value === 'opted_out',
            'unread_count' => (int) $conversation->getAttribute('unread_count'),
            'last_message' => $latest === null ? null : [
                'body' => $latest->body,
                'direction' => $latest->direction->value,
                'status' => $latest->status->value,
                'channel' => $latest->channel->value,
            ],
            'last_message_label' => $this->shortLabel($conversation->last_message_at ?? $latest?->sent_at),
        ];
    }

    private function dayLabel(CarbonInterface $moment): string
    {
        $local = $moment->copy()->timezone($this->clock->timezone());
        $today = $this->clock->now()->startOfDay();

        if ($local->isSameDay($today)) {
            return 'Today';
        }

        if ($local->isSameDay($today->copy()->subDay())) {
            return 'Yesterday';
        }

        return $local->format($local->year === $today->year ? 'l, d M' : 'd M Y');
    }

    private function shortLabel(?CarbonInterface $moment): ?string
    {
        if ($moment === null) {
            return null;
        }

        $local = $moment->copy()->timezone($this->clock->timezone());
        $now = $this->clock->now();

        if ($local->isSameDay($now)) {
            return $local->format('H:i');
        }

        if ($local->isSameDay($now->copy()->subDay())) {
            return 'Yesterday';
        }

        if ($local->greaterThan($now->copy()->subDays(6)->startOfDay())) {
            return $local->format('D');
        }

        return $local->format($local->year === $now->year ? 'd M' : 'd/m/y');
    }
}
