<?php

namespace App\WhatsApp;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class CloudWhatsAppClient implements WhatsAppClient
{
    public function sendText(string $toE164, string $body, string $idempotencyKey): WhatsAppSendResult
    {
        if ($idempotencyKey === '') {
            return WhatsAppSendResult::failed(['error' => 'Missing idempotency key.']);
        }

        $url = rtrim((string) config('whatsapp.graph_url'), '/')
            .'/'
            .config('whatsapp.graph_version')
            .'/'
            .config('whatsapp.phone_number_id')
            .'/messages';

        try {
            $response = Http::withToken((string) config('whatsapp.token'))
                ->acceptJson()
                ->timeout(20)
                ->post($url, [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'to' => ltrim($toE164, '+'),
                    'type' => 'text',
                    'text' => [
                        'preview_url' => false,
                        'body' => $body,
                    ],
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
        $messages = $raw['messages'] ?? null;

        if (is_array($messages) && is_array($messages[0] ?? null)) {
            $candidate = $messages[0]['id'] ?? null;
            $providerId = is_string($candidate) ? $candidate : null;
        }

        if ($providerId === null || $providerId === '') {
            return WhatsAppSendResult::unknown($raw);
        }

        return WhatsAppSendResult::accepted($providerId, $raw);
    }

    public function sendTemplate(string $toE164, string $body, string $idempotencyKey): WhatsAppSendResult
    {
        if ($idempotencyKey === '') {
            return WhatsAppSendResult::failed(['error' => 'Missing idempotency key.']);
        }

        $templateName = (string) config('whatsapp.template.name');
        $language = (string) config('whatsapp.template.language', 'en');

        if ($templateName === '') {
            return WhatsAppSendResult::failed(['error' => 'WHATSAPP_TEMPLATE_NAME is not set.']);
        }

        $url = rtrim((string) config('whatsapp.graph_url'), '/')
            .'/'
            .config('whatsapp.graph_version')
            .'/'
            .config('whatsapp.phone_number_id')
            .'/messages';

        try {
            $response = Http::withToken((string) config('whatsapp.token'))
                ->acceptJson()
                ->timeout(20)
                ->post($url, [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'to' => ltrim($toE164, '+'),
                    'type' => 'template',
                    'template' => [
                        'name' => $templateName,
                        'language' => ['code' => $language],
                        'components' => [
                            [
                                'type' => 'body',
                                'parameters' => [
                                    [
                                        'type' => 'text',
                                        'text' => mb_substr($body, 0, 1024),
                                    ],
                                ],
                            ],
                        ],
                    ],
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
        $messages = $raw['messages'] ?? null;

        if (is_array($messages) && is_array($messages[0] ?? null)) {
            $candidate = $messages[0]['id'] ?? null;
            $providerId = is_string($candidate) ? $candidate : null;
        }

        if ($providerId === null || $providerId === '') {
            return WhatsAppSendResult::unknown($raw);
        }

        return WhatsAppSendResult::accepted($providerId, $raw);
    }
}
