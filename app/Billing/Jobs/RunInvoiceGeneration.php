<?php

namespace App\Billing\Jobs;

use App\Billing\Services\GenerateInvoiceDraft;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunInvoiceGeneration implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public int $generationId) {}

    public function handle(GenerateInvoiceDraft $generator): void
    {
        $generator->run($this->generationId);
    }
}
