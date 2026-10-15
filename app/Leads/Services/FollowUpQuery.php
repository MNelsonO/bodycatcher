<?php

namespace App\Leads\Services;

use App\Leads\Lead;
use App\Settings\DisplayClock;
use Illuminate\Database\Eloquent\Builder;

class FollowUpQuery
{
    public function __construct(private DisplayClock $clock) {}

    /**
     * @param  Builder<Lead>  $query
     * @return Builder<Lead>
     */
    public function apply(Builder $query, string $status): Builder
    {
        $today = $this->clock->today();

        return match ($status) {
            'none' => $query->whereNull('next_follow_up_on'),
            'due_today' => $query->whereDate('next_follow_up_on', $today),
            'overdue' => $query->whereNotNull('next_follow_up_on')->whereDate('next_follow_up_on', '<', $today),
            'upcoming' => $query->whereDate('next_follow_up_on', '>', $today),
            'chat' => $query->where('follow_up_source', 'chat')->whereNotNull('next_follow_up_on'),
            'ranked' => $query->where('follow_up_rank', '>', 0)->orderByDesc('follow_up_rank'),
            default => $query,
        };
    }
}
