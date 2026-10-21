<?php

namespace App\WhatsApp;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class TwilioWhatsAppClient implements WhatsAppClient
{
    public function sendText(string $toE164, string $body, string $idempotencyKey): WhatsAppSendResult
    {
        if ($idempotencyKey === '') {
            return WhatsAppSendResult::failed(['error' => 'Missing idempotency key.']);
        }

        $sid = (string) config('whatsapp.twilio.account_sid');
        $token = (string) config('whatsapp.twilio.auth_token');
        $from = (string) config('whatsapp.twilio.from');

        if ($sid === '' || $token === '' || $from === '') {
            return WhatsAppSendResult::failed(['error' => 'Twilio WhatsApp is not configured.']);
        }

        $to = str_starts_with($toE164, 'whatsapp:') ? $toE164 : 'whatsapp:'.$toE164;
        $fromNumber = str_starts_with($from, 'whatsapp:') ? $from : 'whatsapp:'.$from;

        try {
            $response = Http::withBasicAuth($sid, $token)
                ->asForm()
                ->timeout(20)
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                    'To' => $to,
                    'From' => $fromNumber,
                    'Body' => $body,
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

        $providerId = is_string($raw['sid'] ?? null) ? $raw['sid'] : null;

        if ($providerId === null || $providerId === '') {
            return WhatsAppSendResult::unknown($raw);
        }

        return WhatsAppSendResult::accepted($providerId, $raw);
    }

    public function sendTemplate(string $toE164, string $body, string $idempotencyKey): WhatsAppSendResult
    {
        return $this->sendText($toE164, $body, $idempotencyKey);
    }
}
