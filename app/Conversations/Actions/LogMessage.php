<?php

namespace App\Conversations\Actions;

use App\Conversations\Conversation;
use App\Conversations\Message;
use App\Conversations\MessageDirection;
use App\Leads\Actions\RecordActivity;
use App\Leads\ActivityEvent;
use App\Leads\Lead;
use App\Models\User;
use Illuminate\Support\Carbon;

class LogMessage
{
    public function __construct(private RecordActivity $activity) {}

    public function handle(Lead $lead, MessageDirection $direction, string $body, ?string $sentAt, User $actor): Message
    {
        $conversation = $lead->conversation;

        if ($conversation === null) {
            $conversation = Conversation::query()->create([
                'lead_id' => $lead->id,
                'contact_id' => $lead->contact_id,
            ]);
        }

        $message = Message::query()->create([
            'conversation_id' => $conversation->id,
            'direction' => $direction,
            'body' => trim($body),
            'sent_at' => $sentAt !== null && $sentAt !== '' ? Carbon::parse($sentAt) : now(),
            'logged_by_user_id' => $actor->id,
        ]);

        $this->activity->handle(
            ActivityEvent::MessageLogged,
            $lead,
            $lead->contact,
            $actor,
            [
                'direction' => $direction->value,
                'message_id' => $message->id,
            ],
        );

        return $message;
    }
}
