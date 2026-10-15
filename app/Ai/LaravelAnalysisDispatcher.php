<?php

namespace App\Ai;

use App\Ai\Jobs\RunLeadAnalysis;

class LaravelAnalysisDispatcher implements AnalysisDispatcher
{
    public function dispatch(int $analysisId): void
    {
        RunLeadAnalysis::dispatch($analysisId);
    }
}
