<?php

namespace App\WhatsApp\Services;

/**
 * Turns FlareSend and Twilio webhook bodies into the Meta-shaped payload
 * that InboundWhatsAppProcessor already understands.
 */
class InboundPayloadNormalizer
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function fromFlareSend(array $payload): array
    {
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : $payload;
        $from = $this->digits($this->jid((string) ($data['from'] ?? $payload['from'] ?? '')));
        $message = is_array($data['message'] ?? null) ? $data['message'] : [];
        $type = is_string($message['type'] ?? null) ? $message['type'] : 'text';
        $text = $this->textFrom($message, $data);
        $timestamp = $this->timestamp($data['timestamp'] ?? $payload['timestamp'] ?? null);
        $id = is_string($data['id'] ?? null) && $data['id'] !== ''
            ? $data['id']
            : (is_string($message['id'] ?? null) && $message['id'] !== ''
                ? $message['id']
                : 'flaresend-'.hash('sha256', $from.'|'.$text.'|'.$timestamp));
        $name = $this->displayName($payload, $data, $message, $from);

        return $this->wrap($from, $id, $type, $text, (int) $timestamp, $name);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function fromTwilio(array $payload): array
    {
        $fromRaw = (string) ($payload['WaId'] ?? $payload['From'] ?? '');
        $from = $this->digits(str_replace('whatsapp:', '', $fromRaw));
        $text = (string) ($payload['Body'] ?? '');
        $id = (string) ($payload['MessageSid'] ?? ('twilio-'.hash('sha256', $from.'|'.$text)));
        $name = $this->cleanName((string) ($payload['ProfileName'] ?? ''))
            ?? $this->fallbackName($from);
        $timestamp = now()->timestamp;

        return $this->wrap($from, $id, 'text', $text, $timestamp, $name);
    }

    /**
     * @return array<string, mixed>
     */
    private function wrap(string $from, string $id, string $type, string $text, int $timestamp, string $name): array
    {
        $message = [
            'from' => $from,
            'id' => $id,
            'timestamp' => (string) $timestamp,
            'type' => $type,
        ];

        if ($type === 'text') {
            $message['text'] = ['body' => $text];
        }

        return [
            'object' => 'whatsapp_business_account',
            'provider' => 'normalized',
            'entry' => [[
                'id' => 'INBOUND',
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'messaging_product' => 'whatsapp',
                        'contacts' => [[
                            'profile' => ['name' => $name],
                            'wa_id' => $from,
                        ]],
                        'messages' => [$message],
                    ],
                ]],
            ]],
        ];
    }

    /**
     * @param  array<string, mixed>  $message
     * @param  array<string, mixed>  $data
     */
    private function textFrom(array $message, array $data): string
    {
        if (is_string($message['text'] ?? null) && $message['text'] !== '') {
            return $message['text'];
        }

        $extended = $message['extendedTextMessage'] ?? null;

        if (is_array($extended) && is_string($extended['text'] ?? null)) {
            return $extended['text'];
        }

        if (is_string($message['conversation'] ?? null)) {
            return $message['conversation'];
        }

        foreach (['imageMessage', 'videoMessage', 'documentMessage'] as $media) {
            $block = $message[$media] ?? null;

            if (is_array($block) && is_string($block['caption'] ?? null) && $block['caption'] !== '') {
                return $block['caption'];
            }
        }

        return is_string($data['text'] ?? null) ? $data['text'] : '';
    }

    private function timestamp(mixed $value): int
    {
        if (is_numeric($value)) {
            return (int) $value;
        }

        if (is_string($value)) {
            $parsed = strtotime($value);

            if ($parsed !== false) {
                return $parsed;
            }
        }

        return now()->timestamp;
    }

    /**
     * Pull a display name from the fields FlareSend / Baileys-style payloads use.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $message
     */
    private function displayName(array $payload, array $data, array $message, string $from): string
    {
        $candidates = [
            $data['name'] ?? null,
            $data['pushName'] ?? null,
            $data['pushname'] ?? null,
            $data['notify'] ?? null,
            $data['verifiedName'] ?? null,
            $data['verifiedBizName'] ?? null,
            $data['senderName'] ?? null,
            $data['profileName'] ?? null,
            $data['contactName'] ?? null,
            $message['pushName'] ?? null,
            $message['pushname'] ?? null,
            $payload['name'] ?? null,
            $payload['pushName'] ?? null,
            $payload['ProfileName'] ?? null,
        ];

        $key = is_array($data['key'] ?? null) ? $data['key'] : [];
        $candidates[] = $key['pushName'] ?? null;
        $candidates[] = $key['participant'] ?? null;

        foreach ($candidates as $candidate) {
            $clean = $this->cleanName(is_string($candidate) ? $candidate : '');

            if ($clean !== null) {
                return $clean;
            }
        }

        return $this->fallbackName($from);
    }

    private function cleanName(string $value): ?string
    {
        $name = trim(preg_replace('/\s+/', ' ', $value) ?? '');

        if ($name === '') {
            return null;
        }

        $lower = mb_strtolower($name);
        $junk = [
            'whatsapp contact',
            'whatsapp lead',
            'unknown',
            'null',
            'undefined',
            'contact',
        ];

        if (in_array($lower, $junk, true)) {
            return null;
        }

        // Ignore raw JIDs / bare phone strings masquerading as names.
        if (str_contains($name, '@') || preg_match('/^\+?\d{8,15}$/', $name) === 1) {
            return null;
        }

        return mb_substr($name, 0, 120);
    }

    private function fallbackName(string $from): string
    {
        if ($from === '') {
            return 'WhatsApp contact';
        }

        return 'WhatsApp +'.$from;
    }

    private function jid(string $value): string
    {
        $at = strpos($value, '@');

        return $at === false ? $value : substr($value, 0, $at);
    }

    private function digits(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }
}
