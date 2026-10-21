<?php

namespace App\WhatsApp;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class FlareSendWhatsAppClient implements WhatsAppClient
{
    public function sendText(string $toE164, string $body, string $idempotencyKey): WhatsAppSendResult
    {
        if ($idempotencyKey === '') {
            return WhatsAppSendResult::failed(['error' => 'Missing idempotency key.']);
        }

        $apiKey = (string) config('whatsapp.flaresend.api_key');
        $baseUrl = rtrim((string) config('whatsapp.flaresend.base_url'), '/');

        if ($apiKey === '' || $baseUrl === '') {
            return WhatsAppSendResult::failed(['error' => 'FlareSend is not configured.']);
        }

        $recipient = ltrim($toE164, '+');

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->timeout(20)
                ->withHeaders([
                    'Idempotency-Key' => $idempotencyKey,
                ])
                ->post($baseUrl.'/send-message', [
                    'recipients' => [$recipient],
                    'type' => 'text',
                    'text' => $body,
                ]);
        } catch (ConnectionException $exception) {
            $raw = ['error' => $exception->getMessage()];
            $message = strtolower($exception->getMessage());

            if (str_contains($message, 'timed out') || str_contains($exception->getMessage(), 'cURL error 28')) {
                return WhatsAppSendResult::unknown($raw);
            }

            return WhatsAppSendResult::transient($raw);
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

        $providerId = null;

        foreach (['message_id', 'id', 'wa_message_id'] as $key) {
            $candidate = $raw[$key] ?? null;

            if (is_string($candidate) && $candidate !== '') {
                $providerId = $candidate;
                break;
            }
        }

        if ($providerId === null) {
            $providerId = 'flaresend-'.$idempotencyKey;
        }

        return WhatsAppSendResult::accepted($providerId, $raw);
    }

    public function sendTemplate(string $toE164, string $body, string $idempotencyKey): WhatsAppSendResult
    {
        // FlareSend has no Meta template gate — free-form text is fine.
        return $this->sendText($toE164, $body, $idempotencyKey);
    }
}
