<?php

namespace App\WhatsApp\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Best-effort WhatsApp username lookup via FlareSend when the webhook
 * payload does not include pushName.
 *
 * Formal/personal chats usually have no WhatsApp Business name — we only
 * want the username (pushName / notify), not a verified business title.
 */
class FlareSendContactNameLookup
{
    public function find(string $e164OrDigits): ?string
    {
        $apiKey = (string) config('whatsapp.flaresend.api_key');
        $baseUrl = rtrim((string) config('whatsapp.flaresend.base_url'), '/');

        if ($apiKey === '' || $baseUrl === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $e164OrDigits) ?? '';

        if ($digits === '') {
            return null;
        }

        $paths = [
            '/contacts/'.$digits,
            '/contact/'.$digits,
            '/contacts/'.$digits.'@s.whatsapp.net',
            '/check-number',
        ];

        foreach ($paths as $path) {
            try {
                $request = Http::withToken($apiKey)->acceptJson()->timeout(8);

                $response = str_contains($path, 'check-number')
                    ? $request->post($baseUrl.$path, ['number' => $digits, 'phone' => $digits])
                    : $request->get($baseUrl.$path);

                if (! $response->successful()) {
                    continue;
                }

                /** @var mixed $json */
                $json = $response->json();

                if (! is_array($json)) {
                    continue;
                }

                $name = $this->extract($json);

                if ($name !== null) {
                    return $name;
                }
            } catch (Throwable $exception) {
                Log::debug('FlareSend name lookup skipped.', [
                    'path' => $path,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extract(array $payload): ?string
    {
        $candidates = [
            $payload['pushName'] ?? null,
            $payload['pushname'] ?? null,
            $payload['notify'] ?? null,
            $payload['username'] ?? null,
            $payload['userName'] ?? null,
            $payload['profileName'] ?? null,
            $payload['name'] ?? null,
            // Business titles only if username fields were empty.
            $payload['verifiedName'] ?? null,
            $payload['verifiedBizName'] ?? null,
        ];

        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $candidates[] = $data['pushName'] ?? null;
        $candidates[] = $data['pushname'] ?? null;
        $candidates[] = $data['notify'] ?? null;
        $candidates[] = $data['username'] ?? null;
        $candidates[] = $data['profileName'] ?? null;
        $candidates[] = $data['name'] ?? null;
        $candidates[] = $data['verifiedName'] ?? null;

        foreach ($candidates as $candidate) {
            if (! is_string($candidate)) {
                continue;
            }

            $clean = trim(preg_replace('/\s+/', ' ', $candidate) ?? '');

            if ($clean === '' || str_contains($clean, '@') || preg_match('/^\+?\d{8,15}$/', $clean) === 1) {
                continue;
            }

            $lower = mb_strtolower($clean);

            if (in_array($lower, ['whatsapp contact', 'whatsapp lead', 'unknown', 'null', 'contact'], true)) {
                continue;
            }

            return mb_substr($clean, 0, 120);
        }

        return null;
    }
}
