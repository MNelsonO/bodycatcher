<?php

namespace App\Ai;

interface AnalysisDispatcher
{
    public function dispatch(int $analysisId): void;
}
