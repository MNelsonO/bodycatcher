<?php

namespace App\SalesPipelines\Actions;

use App\SalesPipelines\Pipeline;
use App\SalesPipelines\PipelineStageTransition;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SavePipelineTransitions
{
    /**
     * @param  array<int|string, array<int, int|string>>  $map
     */
    public function handle(Pipeline $pipeline, array $map): void
    {
        $stageIds = $pipeline->stages()->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();

        DB::transaction(function () use ($pipeline, $map, $stageIds): void {
            PipelineStageTransition::query()->where('pipeline_id', $pipeline->id)->delete();

            foreach ($map as $from => $targets) {
                $fromId = (int) $from;

                if (! in_array($fromId, $stageIds, true)) {
                    throw ValidationException::withMessages([
                        'transitions' => 'A transition refers to a stage outside this pipeline.',
                    ]);
                }

                foreach ($targets as $target) {
                    $toId = (int) $target;

                    if ($toId === $fromId) {
                        continue;
                    }

                    if (! in_array($toId, $stageIds, true)) {
                        throw ValidationException::withMessages([
                            'transitions' => 'A transition refers to a stage outside this pipeline.',
                        ]);
                    }

                    PipelineStageTransition::query()->create([
                        'pipeline_id' => $pipeline->id,
                        'from_stage_id' => $fromId,
                        'to_stage_id' => $toId,
                    ]);
                }
            }
        });
    }
}
