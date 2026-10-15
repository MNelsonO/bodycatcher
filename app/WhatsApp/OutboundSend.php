<?php

namespace App\WhatsApp;

use App\Conversations\Message;

final class OutboundSend
{
    public function __construct(
        public Message $message,
        public bool $duplicate,
        public bool $dispatchRetry,
    ) {}
}
