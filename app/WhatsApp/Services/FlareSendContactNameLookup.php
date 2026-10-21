<?php

namespace App\WhatsApp\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Best-effort WhatsApp display-name lookup via FlareSend when the webhook
 * payload does not include pushName / name.
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
            $payload['name'] ?? null,
            $payload['pushName'] ?? null,
            $payload['pushname'] ?? null,
            $payload['notify'] ?? null,
            $payload['verifiedName'] ?? null,
            $payload['verifiedBizName'] ?? null,
            $payload['profileName'] ?? null,
        ];

        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $candidates[] = $data['name'] ?? null;
        $candidates[] = $data['pushName'] ?? null;
        $candidates[] = $data['notify'] ?? null;
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
