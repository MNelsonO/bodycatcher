<?php

namespace App\Leads\Actions;

use App\Leads\ActivityEvent;
use App\Leads\Lead;
use App\Models\User;
use Illuminate\Support\Carbon;

class ScheduleLeadConversation
{
    public function __construct(private RecordActivity $activity) {}

    public function handle(Lead $lead, ?string $at, ?string $nextAction, User $actor): void
    {
        $from = $lead->next_conversation_at?->toIso8601String();

        if ($at === null || trim($at) === '') {
            $lead->next_conversation_at = null;
        } else {
            $lead->next_conversation_at = Carbon::parse($at);
            // Re-opening a schedule clears a hard stop so the agent can talk again.
            $lead->conversations_stopped = false;
            if ($lead->automation_paused) {
                $lead->automation_paused = false;
            }
        }

        if ($nextAction !== null) {
            $trimmed = trim($nextAction);
            $lead->next_action = $trimmed === '' ? null : $trimmed;
        }

        $lead->save();

        $this->activity->handle(ActivityEvent::ConversationScheduled, $lead, $lead->contact, $actor, [
            'from' => $from,
            'to' => $lead->next_conversation_at?->toIso8601String(),
        ]);
    }
}
