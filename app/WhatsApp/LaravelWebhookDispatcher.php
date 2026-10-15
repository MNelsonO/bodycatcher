<?php

namespace App\WhatsApp;

use App\WhatsApp\Jobs\ProcessWhatsAppWebhook;

class LaravelWebhookDispatcher implements WebhookDispatcher
{
    public function dispatch(int $eventId): void
    {
        ProcessWhatsAppWebhook::dispatch($eventId);
    }
}
