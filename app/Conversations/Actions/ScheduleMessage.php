<?php

namespace App\Conversations\Actions;

use App\FollowUps\FollowUpAction;
use App\FollowUps\FollowUpTask;
use App\FollowUps\FollowUpTaskStatus;
use App\Leads\Actions\RecordActivity;
use App\Leads\ActivityEvent;
use App\Leads\Lead;
use App\Models\User;
use App\Settings\DisplayClock;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * A WhatsApp message written now and sent later by `follow-ups:run`. It goes
 * through the same opt-out and 24-hour checks as a manual send at that time.
 */
class ScheduleMessage
{
    public function __construct(
        private DisplayClock $clock,
        private RecordActivity $activity,
    ) {}

    public function handle(Lead $lead, string $body, string $localDateTime, User $actor): FollowUpTask
    {
        if ($lead->closed_at !== null) {
            throw ValidationException::withMessages(['send_at' => 'This lead is closed. Reopen it before scheduling a message.']);
        }

        $due = Carbon::parse($localDateTime, $this->clock->timezone())->utc();

        if ($due->lessThanOrEqualTo(now()->addMinute())) {
            throw ValidationException::withMessages(['send_at' => 'Choose a time at least a minute from now.']);
        }

        $task = FollowUpTask::query()->create([
            'lead_id' => $lead->id,
            'automation_rule_id' => null,
            'scheduled_by_user_id' => $actor->id,
            'idempotency_key' => 'scheduled:'.$lead->id.':'.Str::uuid()->toString(),
            'action' => FollowUpAction::SendWhatsApp,
            'body' => trim($body),
            'due_at' => $due,
            'anchor_at' => null,
            'cancel_on_reply' => false,
            'status' => FollowUpTaskStatus::Pending,
        ]);

        $this->activity->handle(ActivityEvent::MessageScheduled, $lead, $lead->contact, $actor, [
            'task_id' => $task->id,
            'due' => $this->clock->formatDateTime($due),
        ]);

        return $task;
    }
}
