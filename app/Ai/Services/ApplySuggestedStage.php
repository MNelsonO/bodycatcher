<?php

namespace App\Ai\Services;

use App\Ai\AnalysisStatus;
use App\Ai\LeadAnalysis;
use App\Leads\Lead;
use App\Leads\Services\SalesStageTransitionService;
use App\Models\User;
use App\SalesPipelines\PipelineStage;
use Illuminate\Validation\ValidationException;

class ApplySuggestedStage
{
    public function __construct(private SalesStageTransitionService $transitions) {}

    public function apply(LeadAnalysis $analysis, User $actor): Lead
    {
        if ($analysis->status !== AnalysisStatus::Completed || $analysis->suggested_stage_id === null) {
            throw ValidationException::withMessages([
                'stage_id' => 'This analysis has no permitted stage to apply.',
            ]);
        }

        $stage = PipelineStage::query()->find($analysis->suggested_stage_id);

        if ($stage === null) {
            throw ValidationException::withMessages([
                'stage_id' => 'This analysis has no permitted stage to apply.',
            ]);
        }

        $lead = Lead::query()->findOrFail($analysis->lead_id);
        $reason = $analysis->stage_reason;

        return $this->transitions->transition($lead, $stage, $actor, $reason === null || $reason === '' ? null : $reason);
    }
}
