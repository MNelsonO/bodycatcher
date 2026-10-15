<?php

namespace App\Ai\Jobs;

use App\Ai\Services\InboundAssistantRouter;
use App\Leads\Lead;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunConditionalAutoReply implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $leadId,
        public string $inboundBody,
    ) {}

    public function handle(InboundAssistantRouter $router): void
    {
        $lead = Lead::query()->find($this->leadId);

        if ($lead === null) {
            return;
        }

        $router->handle($lead, $this->inboundBody);
    }
}
