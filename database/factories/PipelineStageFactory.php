<?php

namespace Database\Factories;

use App\SalesPipelines\Pipeline;
use App\SalesPipelines\PipelineStage;
use App\SalesPipelines\StageKind;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PipelineStage>
 */
class PipelineStageFactory extends Factory
{
    protected $model = PipelineStage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pipeline_id' => Pipeline::factory(),
            'name' => 'New Lead',
            'kind' => StageKind::Active,
            'position' => 1,
            'default_follow_up_delay_hours' => 24,
            'archived_at' => null,
        ];
    }
}
