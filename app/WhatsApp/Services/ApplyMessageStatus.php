<?php

namespace App\WhatsApp\Services;

use App\Conversations\Message;
use App\Conversations\MessageStatus;
use App\Conversations\MessageStatusEvent;
use Illuminate\Support\Carbon;

class ApplyMessageStatus
{
    /**
     * @param  array<string, mixed>  $status
     */
    public function apply(array $status): void
    {
        $providerId = $status['id'] ?? null;
        $name = $status['status'] ?? null;

        if (! is_string($providerId) || $providerId === '' || ! is_string($name) || $name === '') {
            return;
        }

        $message = Message::query()->where('provider_message_id', $providerId)->first();

        if ($message === null) {
            return;
        }

        $timestamp = $status['timestamp'] ?? null;
        $providerTimestamp = is_scalar($timestamp) ? (string) $timestamp : 'none';
        $occurredAt = is_numeric($timestamp) ? Carbon::createFromTimestamp((int) $timestamp) : null;

        MessageStatusEvent::query()->firstOrCreate(
            [
                'message_id' => $message->id,
                'status' => $name,
                'provider_timestamp' => $providerTimestamp,
            ],
            [
                'occurred_at' => $occurredAt,
                'payload' => $status,
            ],
        );

        $next = $this->nextStatus($message->status, $name);

        if ($next === null || $next === $message->status) {
            return;
        }

        $message->status = $next;
        $message->retryable = false;
        $message->save();
    }

    private function nextStatus(MessageStatus $current, string $incoming): ?MessageStatus
    {
        if ($current === MessageStatus::Refused) {
            return null;
        }

        if ($incoming === 'failed') {
            if ($current === MessageStatus::Read) {
                return null;
            }

            return MessageStatus::Failed;
        }

        $rank = [
            'sent' => 1,
            'delivered' => 2,
            'read' => 3,
        ];

        if (! isset($rank[$incoming])) {
            return null;
        }

        $currentRank = $rank[$current->value] ?? 0;

        if ($rank[$incoming] < $currentRank) {
            return null;
        }

        return MessageStatus::from($incoming);
    }
}
