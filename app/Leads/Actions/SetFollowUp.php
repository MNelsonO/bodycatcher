<?php

namespace App\Leads\Actions;

use App\Leads\ActivityEvent;
use App\Leads\Lead;
use App\Models\User;
use Illuminate\Support\Carbon;

class SetFollowUp
{
    public function __construct(private RecordActivity $activity) {}

    public function handle(Lead $lead, ?string $nextAction, ?string $date, User $actor): Lead
    {
        $previous = $lead->next_follow_up_on?->toDateString();
        $nextAction = $this->clean($nextAction);
        $date = $this->clean($date);

        $lead->next_action = $nextAction;
        $lead->next_follow_up_on = $date === null ? null : Carbon::parse($date);
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
                ],
            );
        }

        return $lead->refresh();
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
