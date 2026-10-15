<?php

namespace App\Console\Commands;

use App\Ai\AnalysisStatus;
use App\Ai\LeadAnalysis;
use App\Ai\Services\AnalyzeLead;
use Illuminate\Console\Command;

class RetryAiWork extends Command
{
    protected $signature = 'ai:retry';

    protected $description = 'Run queued lead analyses when the queue was down';

    public function handle(AnalyzeLead $analyzer): int
    {
        LeadAnalysis::query()
            ->where('status', AnalysisStatus::Queued)
            ->orderBy('id')
            ->each(function (LeadAnalysis $analysis) use ($analyzer): void {
                $analyzer->run($analysis->id);
            });

        return self::SUCCESS;
    }
}
