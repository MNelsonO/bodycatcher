<?php

namespace App\SalesPipelines\Actions;

use App\SalesPipelines\Pipeline;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReorderStages
{
    /**
     * @param  list<int>  $stageIds
     */
    public function handle(Pipeline $pipeline, array $stageIds): void
    {
        DB::transaction(function () use ($pipeline, $stageIds): void {
            $stages = $pipeline->stages()->get()->keyBy('id');

            if ($stages->count() !== count($stageIds) || collect($stageIds)->unique()->count() !== $stages->count()) {
                throw ValidationException::withMessages([
                    'stage_ids' => 'Include every stage on this pipeline when reordering.',
                ]);
            }

            foreach ($stageIds as $index => $stageId) {
                $stage = $stages->get($stageId);

                if ($stage === null) {
                    throw ValidationException::withMessages([
                        'stage_ids' => 'Include every stage on this pipeline when reordering.',
                    ]);
                }

                $stage->update(['position' => $index + 1]);
            }
        });
    }
}
