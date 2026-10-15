<?php

namespace App\WhatsApp\Jobs;

use App\WhatsApp\Services\SendWhatsAppMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RetryWhatsAppSend implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $messageId) {}

    public function handle(SendWhatsAppMessage $sender): void
    {
        $sender->retry($this->messageId);
    }
}
