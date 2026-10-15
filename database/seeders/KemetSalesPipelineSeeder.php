<?php

namespace Database\Seeders;

use App\SalesPipelines\Actions\DefaultTransitionMap;
use App\SalesPipelines\Pipeline;
use App\SalesPipelines\PipelineStage;
use App\SalesPipelines\PipelineStageTransition;
use App\SalesPipelines\StageKind;
use Illuminate\Database\Seeder;

class KemetSalesPipelineSeeder extends Seeder
{
    public function run(): void
    {
        $pipeline = Pipeline::query()->firstOrCreate(
            ['name' => 'Kemet Tech sales'],
            [
                'description' => 'Default desk pipeline for Kemet Tech software sales. Stages and transitions can be changed by the owner.',
                'is_default' => true,
            ],
        );

        if ($pipeline->stages()->exists()) {
            return;
        }

        $definitions = [
            ['New Lead', StageKind::Active, 24],
            ['Contacted', StageKind::Active, 48],
            ['Qualified', StageKind::Active, 48],
            ['Interested', StageKind::Active, 72],
            ['Demo Requested', StageKind::Active, 24],
            ['Demo Scheduled', StageKind::Active, 24],
            ['Demo Completed', StageKind::Active, 48],
            ['Proposal / Price Sent', StageKind::Active, 48],
            ['Negotiation', StageKind::Active, 48],
            ['Won', StageKind::Won, null],
            ['Lost', StageKind::Lost, null],
            ['Nurture / Future Opportunity', StageKind::ArchivedNurture, 336],
            ['Disqualified', StageKind::Lost, null],
        ];

        $stages = collect($definitions)->map(function (array $definition, int $index) use ($pipeline): PipelineStage {
            return $pipeline->stages()->create([
                'name' => $definition[0],
                'kind' => $definition[1],
                'position' => $index + 1,
                'default_follow_up_delay_hours' => $definition[2],
            ]);
        });

        $pairs = app(DefaultTransitionMap::class)->pairs($stages);

        foreach ($pairs as [$from, $to]) {
            PipelineStageTransition::query()->create([
                'pipeline_id' => $pipeline->id,
                'from_stage_id' => $from,
                'to_stage_id' => $to,
            ]);
        }
    }
}
