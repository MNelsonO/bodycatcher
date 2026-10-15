<?php

namespace App\Ai\Services;

use App\Leads\Lead;

/**
 * Live assistant first when enabled. Otherwise fall back to owner rules.
 */
class InboundAssistantRouter
{
    public function __construct(
        private LiveConversationAssistant $live,
        private ConditionalAutoReply $rules,
    ) {}

    public function handle(Lead $lead, string $inboundBody): void
    {
        if ($this->live->enabled()) {
            $this->live->handle($lead, $inboundBody);

            return;
        }

        $this->rules->handle($lead, $inboundBody);
    }
}
