<?php

namespace App\Ai\Jobs;

use App\Ai\Services\RaiseUnfamiliarQuestion;
use App\Leads\Lead;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RaiseUnfamiliarQuestionJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $leadId,
        public string $inboundBody,
    ) {}

    public function handle(RaiseUnfamiliarQuestion $raiser): void
    {
        $lead = Lead::query()->find($this->leadId);

        if ($lead === null) {
            return;
        }

        $raiser->handle($lead, $this->inboundBody, 'inbound');
    }
}
