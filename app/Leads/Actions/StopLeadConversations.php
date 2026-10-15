<?php

namespace App\Leads\Actions;

use App\FollowUps\FollowUpTask;
use App\FollowUps\FollowUpTaskStatus;
use App\FollowUps\Services\CancelFollowUpsOnReply;
use App\Leads\ActivityEvent;
use App\Leads\Lead;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Stops the automated sales agent on one lead: pauses automation, marks
 * conversations stopped, and cancels every pending follow-up task.
 */
class StopLeadConversations
{
    public function __construct(
        private RecordActivity $activity,
        private CancelFollowUpsOnReply $cancellations,
    ) {}

    public function stop(Lead $lead, User $actor, ?string $reason = null): void
    {
        DB::transaction(function () use ($lead, $actor, $reason): void {
            $lead->automation_paused = true;
            $lead->conversations_stopped = true;
            $lead->next_conversation_at = null;
            $lead->save();

            $tasks = FollowUpTask::query()
                ->where('lead_id', $lead->id)
                ->where('status', FollowUpTaskStatus::Pending)
                ->get();

            foreach ($tasks as $task) {
                $this->cancellations->cancel(
                    $task,
                    $reason ?? 'Future conversations were stopped on this lead.',
                    $actor,
                );
            }

            $this->activity->handle(ActivityEvent::MessagingFlagChanged, $lead, $lead->contact, $actor, [
                'action' => 'stop_conversations',
                'label' => 'Future conversations stopped',
            ]);
        });
    }

    public function allow(Lead $lead, User $actor): void
    {
        $lead->automation_paused = false;
        $lead->conversations_stopped = false;
        $lead->save();

        $this->activity->handle(ActivityEvent::MessagingFlagChanged, $lead, $lead->contact, $actor, [
            'action' => 'allow_conversations',
            'label' => 'Future conversations allowed again',
        ]);
    }
}
