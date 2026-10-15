<?php

namespace App\WhatsApp;

interface WebhookDispatcher
{
    public function dispatch(int $eventId): void;
}
