<?php

namespace App\WhatsApp\Services;

use App\Conversations\Conversation;
use App\Conversations\Message;
use App\Conversations\MessageChannel;
use App\Conversations\MessageDirection;
use App\Conversations\MessageOrigin;
use App\Conversations\MessageStatus;
use App\Leads\Actions\RecordActivity;
use App\Leads\ActivityEvent;
use App\Leads\Lead;
use App\Models\User;
use App\WhatsApp\Jobs\RetryWhatsAppSend;
use App\WhatsApp\OutboundSend;
use App\WhatsApp\SendAttempt;
use App\WhatsApp\WhatsAppClient;
use App\WhatsApp\WhatsAppSendResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class SendWhatsAppMessage
{
    public function __construct(
        private WhatsAppClient $client,
        private MessagingGuard $guard,
        private RecordActivity $activity,
    ) {}

    public function send(Lead $lead, string $body, User $actor, MessageOrigin $origin, string $idempotencyKey): OutboundSend
    {
        /** @var OutboundSend $prepared */
        $prepared = DB::transaction(function () use ($lead, $body, $actor, $origin, $idempotencyKey): OutboundSend {
            $locked = Lead::query()->lockForUpdate()->findOrFail($lead->id);
            $existing = Message::query()->where('idempotency_key', $idempotencyKey)->first();

            if ($existing !== null) {
                return new OutboundSend($existing, true, false);
            }

            $refusal = $this->guard->refusal($locked, $origin);

            if ($refusal !== null) {
                return new OutboundSend($this->storeRefusal($locked, $body, $actor, $origin, $refusal), false, false);
            }

            $locked->load('contact');
            $message = $this->storeQueued($locked, $body, $actor, $origin, $idempotencyKey);
            $result = $this->client->sendText($locked->contact->primary_phone, $body, $idempotencyKey);

            return new OutboundSend($message, false, $this->applyAttempt($message, $result));
        });

        if ($prepared->dispatchRetry) {
            $this->dispatchRetry($prepared->message->id);
        }

        if ($prepared->message->status === MessageStatus::Refused) {
            throw ValidationException::withMessages([
                'body' => $prepared->message->refusal_reason ?? 'The message was not sent.',
            ]);
        }

        return new OutboundSend($prepared->message->refresh(), $prepared->duplicate, false);
    }

    public function retry(int $messageId): void
    {
        $retry = DB::transaction(function () use ($messageId): bool {
            $message = Message::query()->lockForUpdate()->find($messageId);

            if ($message === null || $message->status !== MessageStatus::Queued || ! $message->retryable) {
                return false;
            }

            if ($message->send_attempts >= 3) {
                $message->status = MessageStatus::Failed;
                $message->retryable = false;
                $message->save();

                return false;
            }

            $lead = Lead::query()->lockForUpdate()->findOrFail($message->conversation->lead_id);
            $refusal = $this->guard->refusal($lead, $message->origin);

            if ($refusal !== null) {
                $message->status = MessageStatus::Refused;
                $message->retryable = false;
                $message->refusal_reason = $refusal;
                $message->idempotency_key = null;
                $message->save();
                $this->activity->handle(ActivityEvent::MessageRefused, $lead, $lead->contact, null, [
                    'reason' => $refusal,
                    'message_id' => $message->id,
                ]);

                return false;
            }

            $result = $this->client->sendText(
                $lead->contact->primary_phone,
                $message->body,
                (string) $message->idempotency_key,
            );

            return $this->applyAttempt($message, $result);
        });

        if ($retry) {
            $this->dispatchRetry($messageId);
        }
    }

    private function storeRefusal(Lead $lead, string $body, User $actor, MessageOrigin $origin, string $reason): Message
    {
        $conversation = $this->conversation($lead);
        $message = Message::query()->create([
            'conversation_id' => $conversation->id,
            'direction' => MessageDirection::Outbound,
            'channel' => MessageChannel::WhatsApp,
            'origin' => $origin,
            'status' => MessageStatus::Refused,
            'body' => $body,
            'sent_at' => now(),
            'logged_by_user_id' => $actor->id,
            'idempotency_key' => null,
            'refusal_reason' => $reason,
            'retryable' => false,
            'send_attempts' => 0,
        ]);

        $this->activity->handle(ActivityEvent::MessageRefused, $lead, $lead->contact, $actor, [
            'reason' => $reason,
            'message_id' => $message->id,
            'origin' => $origin->value,
        ]);

        return $message;
    }

    private function storeQueued(Lead $lead, string $body, User $actor, MessageOrigin $origin, string $idempotencyKey): Message
    {
        $conversation = $this->conversation($lead);

        return Message::query()->create([
            'conversation_id' => $conversation->id,
            'direction' => MessageDirection::Outbound,
            'channel' => MessageChannel::WhatsApp,
            'origin' => $origin,
            'status' => MessageStatus::Queued,
            'body' => $body,
            'sent_at' => now(),
            'logged_by_user_id' => $actor->id,
            'idempotency_key' => $idempotencyKey,
            'retryable' => false,
            'send_attempts' => 0,
        ]);
    }

    private function applyAttempt(Message $message, WhatsAppSendResult $result): bool
    {
        $message->send_attempts = $message->send_attempts + 1;
        $message->provider_response = $result->raw;

        if ($result->attempt === SendAttempt::Accepted && $result->providerMessageId !== null) {
            $message->status = MessageStatus::Sent;
            $message->provider_message_id = $result->providerMessageId;
            $message->retryable = false;
            $message->save();
            $this->recordSend($message);

            return false;
        }

        if ($result->attempt === SendAttempt::Unknown) {
            $message->status = MessageStatus::Unknown;
            $message->retryable = false;
            $message->save();

            return false;
        }

        if ($result->attempt === SendAttempt::Transient && $message->send_attempts < 3) {
            $message->status = MessageStatus::Queued;
            $message->retryable = true;
            $message->save();

            return true;
        }

        $message->status = MessageStatus::Failed;
        $message->retryable = false;
        $message->save();

        return false;
    }

    private function recordSend(Message $message): void
    {
        $message->loadMissing('conversation.lead.contact');
        $lead = $message->conversation->lead;
        $actor = $message->loggedBy;

        $this->activity->handle(ActivityEvent::MessageLogged, $lead, $lead->contact, $actor, [
            'direction' => MessageDirection::Outbound->value,
            'channel' => MessageChannel::WhatsApp->value,
            'message_id' => $message->id,
            'status' => MessageStatus::Sent->value,
        ]);
    }

    private function conversation(Lead $lead): Conversation
    {
        $conversation = $lead->conversation;

        if ($conversation !== null) {
            return $conversation;
        }

        return Conversation::query()->create([
            'lead_id' => $lead->id,
            'contact_id' => $lead->contact_id,
        ]);
    }

    private function dispatchRetry(int $messageId): void
    {
        try {
            RetryWhatsAppSend::dispatch($messageId);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
