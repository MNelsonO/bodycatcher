<?php

namespace App\WhatsApp;

interface WhatsAppClient
{
    /**
     * Send one free-form text. The idempotency key is a local desk key.
     * The Cloud API call must not be repeated for a message that timed out.
     */
    public function sendText(string $toE164, string $body, string $idempotencyKey): WhatsAppSendResult;
}
