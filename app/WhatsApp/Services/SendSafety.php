<?php

namespace App\WhatsApp\Services;

use App\Conversations\Message;
use App\Conversations\MessageDirection;
use App\Conversations\MessageOrigin;
use App\Conversations\MessageStatus;
use App\Leads\Lead;
use Illuminate\Database\Eloquent\Builder;

/**
 * Keeps sending on WhatsApp Web style connections at a human pace. A
 * non-null return is the reason this send must wait (automatic) or stop
 * (manual). Meta Cloud API and Twilio enforce their own limits and skip this.
 */
class SendSafety
{
    public function applies(): bool
    {
        return (bool) config('whatsapp.safety.enabled', true)
            && in_array((string) config('whatsapp.driver'), ['evolution', 'flaresend'], true);
    }

    public function reason(Lead $lead, MessageOrigin $origin, ?string $body = null): ?string
    {
        if (! $this->applies()) {
            return null;
        }

        return $origin === MessageOrigin::Manual
            ? $this->manualReason($lead)
            : $this->automaticReason($lead, $body);
    }

    /**
     * @return array{automatic_today: int, automatic_last_hour: int, new_chats_today: int, limits: array<string, int>}
     */
    public function usage(): array
    {
        return [
            'automatic_today' => $this->sent(MessageOrigin::Automatic)->where('sent_at', '>=', now()->startOfDay())->count(),
            'automatic_last_hour' => $this->sent(MessageOrigin::Automatic)->where('sent_at', '>=', now()->subHour())->count(),
            'new_chats_today' => $this->newChatsToday(),
            'limits' => [
                'auto_per_hour' => $this->limit('auto_per_hour'),
                'auto_per_day' => $this->limit('auto_per_day'),
                'manual_new_chats_per_day' => $this->limit('manual_new_chats_per_day'),
                'auto_min_gap_seconds' => $this->limit('auto_min_gap_seconds'),
            ],
        ];
    }

    private function automaticReason(Lead $lead, ?string $body): ?string
    {
        if (! $this->hasWrittenToUs($lead)) {
            return 'Safe sending: automatic messages only go to people who have written to you first. Send the first message by hand.';
        }

        $gap = $this->limit('auto_min_gap_seconds');

        if ($gap > 0 && $this->sent(MessageOrigin::Automatic)->where('sent_at', '>', now()->subSeconds($gap))->exists()) {
            return "Safe sending: waiting {$gap}s between automatic messages.";
        }

        $chatGap = $this->limit('auto_per_chat_gap_seconds');

        if ($chatGap > 0 && $this->sent(MessageOrigin::Automatic)
            ->whereHas('conversation', fn (Builder $query) => $query->where('lead_id', $lead->id))
            ->where('sent_at', '>', now()->subSeconds($chatGap))
            ->exists()) {
            return 'Safe sending: this chat just got an automatic message.';
        }

        $hour = $this->limit('auto_per_hour');

        if ($hour > 0 && $this->sent(MessageOrigin::Automatic)->where('sent_at', '>', now()->subHour())->count() >= $hour) {
            return "Safe sending: {$hour} automatic messages already went out in the last hour.";
        }

        $day = $this->limit('auto_per_day');

        if ($day > 0 && $this->sent(MessageOrigin::Automatic)->where('sent_at', '>=', now()->startOfDay())->count() >= $day) {
            return "Safe sending: today's limit of {$day} automatic messages is reached.";
        }

        $same = $this->limit('auto_same_text_chats_per_hour');

        if ($same > 0 && $body !== null && trim($body) !== '') {
            $chats = $this->sent(MessageOrigin::Automatic)
                ->where('sent_at', '>', now()->subHour())
                ->where('body', trim($body))
                ->distinct()
                ->count('conversation_id');

            if ($chats >= $same) {
                return 'Safe sending: the same text already went to several chats this hour.';
            }
        }

        return null;
    }

    private function manualReason(Lead $lead): ?string
    {
        $burst = $this->limit('manual_burst_per_10_minutes');

        if ($burst > 0 && Message::query()
            ->where('direction', MessageDirection::Outbound->value)
            ->where('channel', 'whatsapp')
            ->whereNotNull('logged_by_user_id')
            ->whereNotIn('status', [MessageStatus::Refused->value, MessageStatus::Failed->value])
            ->where('sent_at', '>', now()->subMinutes(10))
            ->count() >= $burst) {
            return "Safe sending: {$burst} messages went out in the last 10 minutes. Pause for a few minutes so WhatsApp does not flag the number.";
        }

        $newChats = $this->limit('manual_new_chats_per_day');

        if ($newChats > 0 && ! $this->hasWrittenToUs($lead) && ! $this->alreadyMessaged($lead) && $this->newChatsToday() >= $newChats) {
            return "Safe sending: {$newChats} new chats were started today. Starting many chats with people who have not written to you is the fastest way to get a number banned.";
        }

        return null;
    }

    /**
     * @return Builder<Message>
     */
    private function sent(MessageOrigin $origin): Builder
    {
        return Message::query()
            ->where('direction', MessageDirection::Outbound->value)
            ->where('origin', $origin->value)
            ->whereNotIn('status', [MessageStatus::Refused->value, MessageStatus::Failed->value]);
    }

    private function hasWrittenToUs(Lead $lead): bool
    {
        return Message::query()
            ->where('direction', MessageDirection::Inbound->value)
            ->where('body', '!=', '')
            ->whereHas('conversation', fn (Builder $query) => $query->where('contact_id', $lead->contact_id))
            ->exists();
    }

    private function alreadyMessaged(Lead $lead): bool
    {
        return Message::query()
            ->where('direction', MessageDirection::Outbound->value)
            ->where('channel', 'whatsapp')
            ->where('sent_at', '<', now()->startOfDay())
            ->whereHas('conversation', fn (Builder $query) => $query->where('contact_id', $lead->contact_id))
            ->exists();
    }

    /**
     * Chats started today: people who have never written to us and got their
     * first WhatsApp message from the desk today.
     */
    private function newChatsToday(): int
    {
        return Message::query()
            ->where('messages.direction', MessageDirection::Outbound->value)
            ->where('messages.channel', 'whatsapp')
            ->whereNotIn('messages.status', [MessageStatus::Refused->value, MessageStatus::Failed->value])
            ->where('messages.sent_at', '>=', now()->startOfDay())
            ->join('conversations', 'conversations.id', '=', 'messages.conversation_id')
            ->whereNotExists(function ($query): void {
                $query->from('messages as earlier')
                    ->join('conversations as c2', 'c2.id', '=', 'earlier.conversation_id')
                    ->whereColumn('c2.contact_id', 'conversations.contact_id')
                    ->where(function ($inner): void {
                        $inner->where('earlier.direction', MessageDirection::Inbound->value)
                            ->orWhere('earlier.sent_at', '<', now()->startOfDay());
                    });
            })
            ->distinct()
            ->count('conversations.contact_id');
    }

    private function limit(string $key): int
    {
        return (int) config('whatsapp.safety.'.$key, 0);
    }
}
