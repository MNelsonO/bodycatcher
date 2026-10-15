<?php

namespace App\Ai\Jobs;

use App\Ai\Services\AnalyzeLead;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunLeadAnalysis implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public int $analysisId) {}

    public function handle(AnalyzeLead $analyzer): void
    {
        $analyzer->run($this->analysisId);
    }
}
