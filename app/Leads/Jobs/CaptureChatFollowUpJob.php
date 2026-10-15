<?php

namespace App\Leads\Jobs;

use App\Leads\Lead;
use App\Leads\Services\CaptureChatFollowUp;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CaptureChatFollowUpJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $leadId,
        public string $inboundBody,
    ) {}

    public function handle(CaptureChatFollowUp $capture): void
    {
        $lead = Lead::query()->find($this->leadId);

        if ($lead === null) {
            return;
        }

        $capture->handle($lead, $this->inboundBody);
    }
}
