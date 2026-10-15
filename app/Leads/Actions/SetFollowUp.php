<?php

namespace App\Leads\Actions;

use App\Leads\ActivityEvent;
use App\Leads\Lead;
use App\Leads\Services\FollowUpRanker;
use App\Models\User;
use Illuminate\Support\Carbon;

class SetFollowUp
{
    public function __construct(
        private RecordActivity $activity,
        private FollowUpRanker $ranker,
    ) {}

    public function handle(Lead $lead, ?string $nextAction, ?string $date, User $actor, string $source = 'manual'): Lead
    {
        $previous = $lead->next_follow_up_on?->toDateString();
        $nextAction = $this->clean($nextAction);
        $date = $this->clean($date);

        $lead->next_action = $nextAction;
        $lead->next_follow_up_on = $date === null ? null : Carbon::parse($date);

        $lead->follow_up_source = $date === null ? null : $source;

        $lead->save();

        if ($previous !== $date) {
            $this->activity->handle(
                ActivityEvent::FollowUpChanged,
                $lead,
                $lead->contact,
                $actor,
                [
                    'from' => $previous,
                    'to' => $date,
                    'source' => $lead->follow_up_source,
                ],
            );
        }

        return $this->ranker->refresh($lead->fresh(['currentStage']) ?? $lead->refresh());
    }

    private function clean(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
