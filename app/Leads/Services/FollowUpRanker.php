<?php

namespace App\Leads\Services;

use App\Leads\Lead;
use App\SalesPipelines\DefaultKemetStages;
use App\Settings\DisplayClock;
use Illuminate\Support\Facades\DB;

/**
 * Scores leads so agents work the hottest follow-ups first.
 */
class FollowUpRanker
{
    public function __construct(private DisplayClock $clock) {}

    public function refresh(Lead $lead): Lead
    {
        [$rank, $reason] = $this->score($lead);

        $lead->follow_up_rank = $rank;
        $lead->follow_up_rank_reason = $reason;
        $lead->save();

        return $lead;
    }

    /**
     * @return array{0: int, 1: string}
     */
    public function score(Lead $lead): array
    {
        $today = $this->clock->today();
        $now = $this->clock->now();
        $rank = 0;
        $reasons = [];

        $followOn = $lead->next_follow_up_on?->toDateString();

        if ($followOn !== null) {
            if ($followOn < $today) {
                $days = max(1, (int) $lead->next_follow_up_on->diffInDays($now->copy()->startOfDay()));
                $rank = max($rank, min(100, 92 + min($days, 8)));
                $reasons[] = 'Overdue '.$days.'d';
            } elseif ($followOn === $today) {
                $rank = max($rank, 88);
                $reasons[] = 'Due today';
            } else {
                $days = (int) $now->copy()->startOfDay()->diffInDays($lead->next_follow_up_on);
                if ($days <= 3) {
                    $rank = max($rank, 72);
                    $reasons[] = 'Due in '.$days.'d';
                } elseif ($days <= 7) {
                    $rank = max($rank, 58);
                    $reasons[] = 'Due this week';
                } else {
                    $rank = max($rank, 40);
                    $reasons[] = 'Upcoming follow-up';
                }
            }
        }

        if ($lead->next_conversation_at !== null && ! $lead->conversations_stopped) {
            $hours = $now->diffInHours($lead->next_conversation_at, false);

            if ($hours >= -2 && $hours <= 24) {
                $rank = max($rank, 90);
                $reasons[] = 'Talk soon';
            } elseif ($hours > 24 && $hours <= 72) {
                $rank = max($rank, 68);
                $reasons[] = 'Talk in 1–3 days';
            }
        }

        if ($lead->follow_up_source === 'chat') {
            $rank = min(100, $rank + 6);
            $reasons[] = 'From chat';
        }

        $stageName = $lead->relationLoaded('currentStage')
            ? $lead->currentStage?->name
            : $lead->currentStage()->value('name');

        if (is_string($stageName) && in_array($stageName, DefaultKemetStages::hotStages(), true)) {
            $rank = min(100, $rank + 8);
            $reasons[] = 'Hot stage';
        }

        $score = DB::table('lead_analyses')
            ->where('lead_id', $lead->id)
            ->where('status', 'completed')
            ->whereNotNull('score')
            ->orderByDesc('id')
            ->value('score');

        if (is_numeric($score) && (int) $score >= 70) {
            $rank = min(100, $rank + 7);
            $reasons[] = 'AI '.$score;
        }

        if ($rank === 0) {
            return [0, 'No follow-up yet'];
        }

        return [$rank, implode(' · ', array_slice($reasons, 0, 3))];
    }
}
