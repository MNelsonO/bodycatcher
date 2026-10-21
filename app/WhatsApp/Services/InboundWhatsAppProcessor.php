<?php

namespace App\WhatsApp\Services;

use App\Ai\Jobs\RunConditionalAutoReply;
use App\Contacts\ConsentStatus;
use App\Conversations\Conversation;
use App\Conversations\Message;
use App\Conversations\MessageAttachment;
use App\Conversations\MessageChannel;
use App\Conversations\MessageDirection;
use App\Conversations\MessageOrigin;
use App\Conversations\MessageStatus;
use App\FollowUps\Services\CancelFollowUpsOnReply;
use App\Leads\Actions\RecordActivity;
use App\Leads\ActivityEvent;
use App\Ai\Jobs\RaiseUnfamiliarQuestionJob;
use App\Leads\Jobs\CaptureChatFollowUpJob;
use App\WhatsApp\OptOutKeywords;
use App\WhatsApp\WebhookEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class InboundWhatsAppProcessor
{
    public function __construct(
        private InboundConversationResolver $resolver,
        private ApplyMessageStatus $statuses,
        private RecordActivity $activity,
        private CancelFollowUpsOnReply $cancellations,
    ) {}

    public function process(int $eventId): void
    {
        DB::transaction(function () use ($eventId): void {
            $event = WebhookEvent::query()->lockForUpdate()->find($eventId);

            if ($event === null || $event->processed_at !== null) {
                return;
            }

            $this->handlePayload($event->payload);
            $event->processed_at = now();
            $event->processing_error = null;
            $event->save();
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function handlePayload(array $payload): void
    {
        $entries = $payload['entry'] ?? null;

        if (! is_array($entries)) {
            return;
        }

        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $changes = $entry['changes'] ?? null;

            if (! is_array($changes)) {
                continue;
            }

            foreach ($changes as $change) {
                if (! is_array($change)) {
                    continue;
                }

                $value = $change['value'] ?? null;

                if (! is_array($value)) {
                    continue;
                }

                /** @var array<string, mixed> $value */
                $this->handleValue($value);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function handleValue(array $value): void
    {
        $messages = $value['messages'] ?? null;

        if (is_array($messages)) {
            $contacts = $value['contacts'] ?? [];
            $profiles = is_array($contacts) ? $contacts : [];

            foreach ($messages as $message) {
                if (is_array($message)) {
                    /** @var array<string, mixed> $message */
                    $this->ingestMessage($message, $profiles);
                }
            }
        }

        $statuses = $value['statuses'] ?? null;

        if (! is_array($statuses)) {
            return;
        }

        foreach ($statuses as $status) {
            if (is_array($status)) {
                /** @var array<string, mixed> $status */
                $this->statuses->apply($status);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $message
     * @param  array<mixed>  $profiles
     */
    private function ingestMessage(array $message, array $profiles): void
    {
        $providerId = $message['id'] ?? null;
        $from = $message['from'] ?? null;

        if (! is_string($providerId) || $providerId === '' || ! is_string($from) || $from === '') {
            return;
        }

        if (Message::query()->where('provider_message_id', $providerId)->exists()) {
            return;
        }

        $type = is_string($message['type'] ?? null) ? $message['type'] : 'unknown';
        $parsed = $this->content($message, $type);
        $timestamp = $message['timestamp'] ?? null;
        $sentAt = is_numeric($timestamp) ? Carbon::createFromTimestamp((int) $timestamp) : now();

        $lead = $this->resolver->resolve($from, $this->profileName($profiles, $from));
        $conversation = $lead->conversation ?? Conversation::query()->create([
            'lead_id' => $lead->id,
            'contact_id' => $lead->contact_id,
        ]);

        $stored = Message::query()->create([
            'conversation_id' => $conversation->id,
            'direction' => MessageDirection::Inbound,
            'channel' => MessageChannel::WhatsApp,
            'origin' => MessageOrigin::Inbound,
            'status' => MessageStatus::Received,
            'body' => $parsed['body'],
            'sent_at' => $sentAt,
            'logged_by_user_id' => null,
            'provider_message_id' => $providerId,
            'retryable' => false,
            'send_attempts' => 0,
        ]);

        if ($parsed['media_id'] !== null) {
            MessageAttachment::query()->create([
                'message_id' => $stored->id,
                'provider_media_id' => $parsed['media_id'],
                'mime_type' => $parsed['mime_type'],
                'caption' => $parsed['caption'],
            ]);
        }

        $this->activity->handle(ActivityEvent::MessageLogged, $lead, $lead->contact, null, [
            'direction' => MessageDirection::Inbound->value,
            'channel' => MessageChannel::WhatsApp->value,
            'message_id' => $stored->id,
        ]);

        $this->cancellations->handle($lead, $sentAt);

        if ($type === 'text' && OptOutKeywords::matches($parsed['body'])) {
            $contact = $lead->contact;
            $contact->consent_status = ConsentStatus::OptedOut;
            $contact->opted_out_at = $sentAt;
            $contact->save();

            $this->activity->handle(ActivityEvent::ContactOptedOut, $lead, $contact, null, [
                'keyword' => mb_strtolower(trim($parsed['body'])),
            ]);
        } elseif ($type === 'text' && $parsed['body'] !== '') {
            CaptureChatFollowUpJob::dispatch($lead->id, $parsed['body']);
            RaiseUnfamiliarQuestionJob::dispatch($lead->id, $parsed['body']);
            RunConditionalAutoReply::dispatch($lead->id, $parsed['body']);
        }

        $lead->touch();
    }

    /**
     * @param  array<mixed>  $profiles
     */
    private function profileName(array $profiles, string $waId): ?string
    {
        $want = preg_replace('/\D+/', '', $waId) ?? $waId;

        foreach ($profiles as $profile) {
            if (! is_array($profile)) {
                continue;
            }

            $candidateId = preg_replace('/\D+/', '', (string) ($profile['wa_id'] ?? '')) ?? '';

            if ($candidateId !== '' && $candidateId !== $want) {
                continue;
            }

            $profileBlock = $profile['profile'] ?? null;

            if (! is_array($profileBlock)) {
                if ($candidateId === $want) {
                    return null;
                }

                continue;
            }

            $name = $profileBlock['name'] ?? null;

            if (is_string($name) && trim($name) !== '') {
                return trim($name);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $message
     * @return array{body: string, media_id: ?string, mime_type: ?string, caption: ?string}
     */
    private function content(array $message, string $type): array
    {
        if ($type === 'text') {
            $text = $message['text'] ?? null;
            $body = is_array($text) && is_string($text['body'] ?? null) ? $text['body'] : '';

            return [
                'body' => $body,
                'media_id' => null,
                'mime_type' => null,
                'caption' => null,
            ];
        }

        $block = $message[$type] ?? null;

        if (! is_array($block)) {
            return [
                'body' => '['.$type.' message]',
                'media_id' => null,
                'mime_type' => null,
                'caption' => null,
            ];
        }

        $mediaId = is_string($block['id'] ?? null) ? $block['id'] : null;
        $mime = is_string($block['mime_type'] ?? null) ? $block['mime_type'] : null;
        $caption = is_string($block['caption'] ?? null) ? $block['caption'] : null;
        $body = $caption !== null && $caption !== '' ? $caption : '['.$type.' attachment]';

        return [
            'body' => $body,
            'media_id' => $mediaId,
            'mime_type' => $mime,
            'caption' => $caption,
        ];
    }
}
