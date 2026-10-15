<?php

namespace App\Leads;

use Illuminate\Support\Carbon;

/**
 * A date/time the contact stated in chat that the desk should follow up on.
 */
final class ChatScheduleHint
{
    public function __construct(
        public Carbon $followUpOn,
        public ?Carbon $conversationAt,
        public string $action,
        public string $matchedSnippet,
        public int $confidence,
    ) {}
}
