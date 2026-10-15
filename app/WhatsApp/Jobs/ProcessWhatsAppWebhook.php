<?php

namespace App\WhatsApp\Jobs;

use App\WhatsApp\Services\InboundWhatsAppProcessor;
use App\WhatsApp\WebhookEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProcessWhatsAppWebhook implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $eventId) {}

    public function handle(InboundWhatsAppProcessor $processor): void
    {
        $processor->process($this->eventId);
    }

    public function failed(Throwable $exception): void
    {
        WebhookEvent::query()->whereKey($this->eventId)->update([
            'processing_error' => $exception->getMessage(),
        ]);
    }
}
