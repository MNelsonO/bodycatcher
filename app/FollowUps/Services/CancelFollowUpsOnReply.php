<?php

namespace App\FollowUps\Services;

use App\FollowUps\FollowUpTask;
use App\FollowUps\FollowUpTaskStatus;
use App\Leads\Actions\RecordActivity;
use App\Leads\ActivityEvent;
use App\Leads\Lead;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class CancelFollowUpsOnReply
{
    public function __construct(private RecordActivity $activity) {}

    public function handle(Lead $lead, CarbonInterface $sentAt): void
    {
        $tasks = FollowUpTask::query()
            ->where('lead_id', $lead->id)
            ->where('status', FollowUpTaskStatus::Pending)
            ->where('cancel_on_reply', true)
            ->where(function ($query) use ($sentAt): void {
                $query->whereNull('anchor_at')->orWhere('anchor_at', '<=', $sentAt);
            })
            ->get();

        foreach ($tasks as $task) {
            $this->cancel($task, 'The contact replied, so this no-response follow-up was cancelled.');
        }
    }

    public function cancel(FollowUpTask $task, string $reason, ?User $actor = null): void
    {
        DB::transaction(function () use ($task, $reason, $actor): void {
            $locked = FollowUpTask::query()->lockForUpdate()->find($task->id);

            if ($locked === null || $locked->status !== FollowUpTaskStatus::Pending) {
                return;
            }

            $locked->status = FollowUpTaskStatus::Cancelled;
            $locked->failure_reason = $reason;
            $locked->held_reason = null;
            $locked->executed_at = now();
            $locked->save();
            $locked->load('lead.contact');

            $this->activity->handle(ActivityEvent::FollowUpCancelled, $locked->lead, $locked->lead->contact, $actor, [
                'task_id' => $locked->id,
                'reason' => $reason,
            ]);
        });
    }
}
