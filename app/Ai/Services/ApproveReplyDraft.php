<?php

namespace App\Ai\Services;

use App\Ai\DraftStatus;
use App\Ai\ReplyDraft;
use App\Conversations\Message;
use App\Conversations\MessageOrigin;
use App\Conversations\MessageStatus;
use App\Leads\Actions\RecordActivity;
use App\Leads\ActivityEvent;
use App\Leads\Lead;
use App\Models\User;
use App\WhatsApp\Services\SendWhatsAppMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApproveReplyDraft
{
    public function __construct(
        private SendWhatsAppMessage $sender,
        private RecordActivity $activity,
    ) {}

    public function save(ReplyDraft $draft, string $body, User $actor): ReplyDraft
    {
        return $this->storeBody($draft, $body, $actor);
    }

    public function send(ReplyDraft $draft, string $body, User $actor): ReplyDraft
    {
        $draft = $this->storeBody($draft, $body, $actor);
        $lead = Lead::query()->findOrFail($draft->lead_id);
        $existing = $this->latestAttempt($draft);

        if ($existing !== null && ! $this->canRetry($existing)) {
            return $this->markSent($draft, $existing, $actor);
        }

        $key = $existing === null || $existing->status === MessageStatus::Refused
            ? 'ai-draft-'.$draft->id
            : 'ai-draft-'.$draft->id.'-'.$existing->id;

        $result = $this->sender->send($lead, $draft->body, $actor, MessageOrigin::Manual, $key);
        $message = $result->message;

        if ($message->status === MessageStatus::Refused || $message->status === MessageStatus::Failed) {
            return $draft->refresh();
        }

        return $this->markSent($draft, $message, $actor);
    }

    private function storeBody(ReplyDraft $draft, string $body, User $actor): ReplyDraft
    {
        return DB::transaction(function () use ($draft, $body, $actor): ReplyDraft {
            $locked = ReplyDraft::query()->lockForUpdate()->findOrFail($draft->id);

            if ($locked->status !== DraftStatus::Pending) {
                throw ValidationException::withMessages([
                    'body' => 'This draft is already settled.',
                ]);
            }

            $next = trim($body);

            if ($next === '') {
                throw ValidationException::withMessages([
                    'body' => 'Write the reply before saving it.',
                ]);
            }

            $changed = $locked->body !== $next;
            $locked->body = $next;
            $locked->save();

            if ($changed) {
                $locked->load('lead.contact');
                $this->activity->handle(ActivityEvent::DraftEdited, $locked->lead, $locked->lead->contact, $actor, [
                    'draft_id' => $locked->id,
                ]);
            }

            return $locked;
        });
    }

    private function latestAttempt(ReplyDraft $draft): ?Message
    {
        $prefix = 'ai-draft-'.$draft->id;

        return Message::query()
            ->where(function ($query) use ($prefix): void {
                $query->where('idempotency_key', $prefix)
                    ->orWhere('idempotency_key', 'like', $prefix.'-%');
            })
            ->orderByDesc('id')
            ->first();
    }

    private function canRetry(Message $message): bool
    {
        return $message->status === MessageStatus::Failed || $message->status === MessageStatus::Refused;
    }

    private function markSent(ReplyDraft $draft, Message $message, User $actor): ReplyDraft
    {
        return DB::transaction(function () use ($draft, $message, $actor): ReplyDraft {
            $locked = ReplyDraft::query()->lockForUpdate()->findOrFail($draft->id);

            if ($locked->status === DraftStatus::Sent) {
                return $locked;
            }

            $locked->status = DraftStatus::Sent;
            $locked->message_id = $message->id;
            $locked->approved_by_user_id = $actor->id;
            $locked->approved_at = now();
            $locked->save();
            $locked->load('lead.contact');
            $this->activity->handle(ActivityEvent::DraftSent, $locked->lead, $locked->lead->contact, $actor, [
                'draft_id' => $locked->id,
                'message_id' => $message->id,
            ]);

            return $locked;
        });
    }
}
