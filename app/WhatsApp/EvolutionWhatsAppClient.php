<?php

namespace App\WhatsApp;

use Illuminate\Http\Client\ConnectionException;

class EvolutionWhatsAppClient implements WhatsAppClient
{
    public function __construct(private EvolutionGateway $gateway) {}

    public function sendText(string $toE164, string $body, string $idempotencyKey): WhatsAppSendResult
    {
        if ($idempotencyKey === '') {
            return WhatsAppSendResult::failed(['error' => 'Missing idempotency key.']);
        }

        if (! $this->gateway->configured()) {
            return WhatsAppSendResult::failed(['error' => 'The WhatsApp gateway is not configured.']);
        }

        try {
            $response = $this->gateway->sendText(ltrim($toE164, '+'), $body);
        } catch (ConnectionException $exception) {
            $raw = ['error' => $exception->getMessage()];

            return str_contains($exception->getMessage(), 'cURL error 28')
                ? WhatsAppSendResult::unknown($raw)
                : WhatsAppSendResult::transient($raw);
        }

        /** @var mixed $decoded */
        $decoded = $response->json();
        $raw = is_array($decoded) ? $decoded : ['body' => $response->body()];

        if ($response->status() === 429 || $response->serverError()) {
            return WhatsAppSendResult::transient($raw);
        }

        if ($response->failed()) {
            return WhatsAppSendResult::failed($raw);
        }

        // The real WhatsApp id, so the gateway's own echo of this send is
        // recognised and not stored twice.
        $id = $raw['key']['id'] ?? null;

        return WhatsAppSendResult::accepted(is_string($id) && $id !== '' ? $id : 'evolution-'.$idempotencyKey, $raw);
    }

    public function sendTemplate(string $toE164, string $body, string $idempotencyKey): WhatsAppSendResult
    {
        return $this->sendText($toE164, $body, $idempotencyKey);
    }
}
