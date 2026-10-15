<?php

namespace App\Console\Commands;

use App\Ai\AnalysisStatus;
use App\Ai\LeadAnalysis;
use App\Ai\Services\AnalyzeLead;
use App\Billing\InvoiceGeneration;
use App\Billing\Services\GenerateInvoiceDraft;
use Illuminate\Console\Command;

class RetryAiWork extends Command
{
    protected $signature = 'ai:retry';

    protected $description = 'Run queued lead analyses and invoice generations when the queue was down';

    public function handle(AnalyzeLead $analyzer, GenerateInvoiceDraft $invoices): int
    {
        LeadAnalysis::query()
            ->where('status', AnalysisStatus::Queued)
            ->orderBy('id')
            ->each(function (LeadAnalysis $analysis) use ($analyzer): void {
                $analyzer->run($analysis->id);
            });

        InvoiceGeneration::query()
            ->where('status', AnalysisStatus::Queued)
            ->orderBy('id')
            ->each(function (InvoiceGeneration $generation) use ($invoices): void {
                $invoices->run($generation->id);
            });

        return self::SUCCESS;
    }
}
