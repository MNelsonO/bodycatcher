<?php

namespace App\SalesPipelines\Actions;

use App\SalesPipelines\PipelineStage;
use App\SalesPipelines\StageKind;
use Illuminate\Support\Collection;

class DefaultTransitionMap
{
    /**
     * Forward one step, backward one step, and any active stage to a closed stage.
     *
     * @param  Collection<int, PipelineStage>  $stages
     * @return list<array{0: int, 1: int}>
     */
    public function pairs(Collection $stages): array
    {
        $ordered = $stages->sortBy('position')->values();
        $pairs = [];

        foreach ($ordered as $index => $stage) {
            if ($index > 0) {
                $previous = $ordered->get($index - 1);

                if ($previous instanceof PipelineStage) {
                    $pairs[$stage->id.'-'.$previous->id] = [$stage->id, $previous->id];
                }
            }

            $next = $ordered->get($index + 1);

            if ($next instanceof PipelineStage) {
                $pairs[$stage->id.'-'.$next->id] = [$stage->id, $next->id];
            }

            if ($stage->kind !== StageKind::Active) {
                continue;
            }

            foreach ($ordered as $target) {
                if ($target->kind === StageKind::Active || $target->id === $stage->id) {
                    continue;
                }

                $pairs[$stage->id.'-'.$target->id] = [$stage->id, $target->id];
            }
        }

        return array_values($pairs);
    }
}
