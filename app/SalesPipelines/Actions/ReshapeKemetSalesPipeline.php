<?php

namespace App\SalesPipelines\Actions;

use App\Leads\Lead;
use App\Leads\LeadStageHistory;
use App\SalesPipelines\DefaultKemetStages;
use App\SalesPipelines\Pipeline;
use App\SalesPipelines\PipelineStage;
use App\SalesPipelines\PipelineStageTransition;
use Illuminate\Support\Facades\DB;

/**
 * Aligns the default Kemet Tech sales pipeline to the canonical stage set.
 * Safe to run repeatedly: renames, merges, creates missing stages, rebuilds transitions.
 */
class ReshapeKemetSalesPipeline
{
    public function __construct(private DefaultTransitionMap $transitions) {}

    public function handle(?Pipeline $pipeline = null): Pipeline
    {
        $pipeline ??= Pipeline::query()->firstOrCreate(
            ['name' => DefaultKemetStages::PIPELINE_NAME],
            [
                'description' => 'Kemet Tech software sales. Stages move from first contact → demo → close.',
                'is_default' => true,
            ],
        );

        $pipeline->update([
            'description' => 'Kemet Tech software sales. Stages move from first contact → demo → close.',
            'is_default' => true,
        ]);

        DB::transaction(function () use ($pipeline): void {
            $this->ensureCanonicalStages($pipeline);
            $this->mergeLegacyStages($pipeline);
            $this->reorderAndRefresh($pipeline);
            $this->rebuildTransitions($pipeline);
        });

        return $pipeline->fresh(['stages']);
    }

    private function ensureCanonicalStages(Pipeline $pipeline): void
    {
        $renameMap = DefaultKemetStages::renameMap();
        $existing = $pipeline->stages()->get()->keyBy('name');

        foreach ($renameMap as $oldName => $newName) {
            if ($oldName === $newName) {
                continue;
            }

            $old = $existing->get($oldName);

            if (! $old instanceof PipelineStage) {
                continue;
            }

            $target = $existing->get($newName);

            if ($target instanceof PipelineStage) {
                $this->moveLeads($old, $target);
                $this->rewireHistory($old, $target);
                $old->delete();
                $existing->forget($oldName);
            } else {
                $old->update(['name' => $newName]);
                $existing->forget($oldName);
                $existing->put($newName, $old->fresh());
            }
        }

        foreach (DefaultKemetStages::definitions() as $index => $definition) {
            $stage = $pipeline->stages()->where('name', $definition['name'])->first();

            if ($stage === null) {
                $pipeline->stages()->create([
                    'name' => $definition['name'],
                    'kind' => $definition['kind'],
                    'position' => $index + 1,
                    'default_follow_up_delay_hours' => $definition['delay'],
                    'purpose' => $definition['purpose'],
                    'archived_at' => null,
                ]);

                continue;
            }

            $stage->update([
                'kind' => $definition['kind'],
                'position' => $index + 1,
                'default_follow_up_delay_hours' => $definition['delay'],
                'purpose' => $definition['purpose'],
                'archived_at' => null,
            ]);
        }
    }

    private function mergeLegacyStages(Pipeline $pipeline): void
    {
        $canonical = collect(DefaultKemetStages::definitions())->pluck('name')->all();
        $renameMap = DefaultKemetStages::renameMap();

        foreach ($pipeline->stages()->get() as $stage) {
            if (in_array($stage->name, $canonical, true)) {
                continue;
            }

            $targetName = $renameMap[$stage->name] ?? 'Lost';
            $target = $pipeline->stages()->where('name', $targetName)->first()
                ?? $pipeline->stages()->where('name', 'Lost')->first();

            if ($target instanceof PipelineStage && $target->id !== $stage->id) {
                $this->moveLeads($stage, $target);
                $this->rewireHistory($stage, $target);
            }

            $stage->delete();
        }
    }

    private function reorderAndRefresh(Pipeline $pipeline): void
    {
        foreach (DefaultKemetStages::definitions() as $index => $definition) {
            $pipeline->stages()
                ->where('name', $definition['name'])
                ->update([
                    'kind' => $definition['kind']->value,
                    'position' => $index + 1,
                    'default_follow_up_delay_hours' => $definition['delay'],
                    'purpose' => $definition['purpose'],
                    'archived_at' => null,
                ]);
        }
    }

    private function rebuildTransitions(Pipeline $pipeline): void
    {
        PipelineStageTransition::query()->where('pipeline_id', $pipeline->id)->delete();

        $stages = $pipeline->stages()->orderBy('position')->get();
        $pairs = $this->transitions->pairs($stages);

        foreach ($pairs as [$from, $to]) {
            PipelineStageTransition::query()->create([
                'pipeline_id' => $pipeline->id,
                'from_stage_id' => $from,
                'to_stage_id' => $to,
            ]);
        }
    }

    private function moveLeads(PipelineStage $from, PipelineStage $to): void
    {
        Lead::query()
            ->where('current_stage_id', $from->id)
            ->update(['current_stage_id' => $to->id]);
    }

    private function rewireHistory(PipelineStage $from, PipelineStage $to): void
    {
        LeadStageHistory::query()
            ->where('from_stage_id', $from->id)
            ->update(['from_stage_id' => $to->id]);

        LeadStageHistory::query()
            ->where('to_stage_id', $from->id)
            ->update(['to_stage_id' => $to->id]);
    }
}
