<?php

namespace App\Http\Controllers;

use App\WhatsApp\Services\InboundPayloadNormalizer;
use App\WhatsApp\WebhookDispatcher;
use App\WhatsApp\WebhookEvent;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Throwable;

class EvolutionWebhookController extends Controller
{
    public function store(
        Request $request,
        string $token,
        InboundPayloadNormalizer $normalizer,
        WebhookDispatcher $dispatcher,
    ): Response {
        $expected = (string) config('whatsapp.evolution.webhook_token');

        if ($expected === '' || ! hash_equals($expected, $token)) {
            return response('Not found.', 404);
        }

        /** @var array<string, mixed> $payload */
        $payload = $request->all();
        $event = mb_strtolower(str_replace('_', '.', (string) ($payload['event'] ?? '')));

        if ($event === 'connection.update') {
            $state = $payload['data']['state'] ?? null;

            if (is_string($state)) {
                Cache::put('evolution.state', $state, now()->addDay());
            }

            return response('OK', 200);
        }

        if ($event === 'qrcode.updated') {
            $qr = $payload['data']['qrcode']['base64'] ?? $payload['data']['base64'] ?? null;

            if (is_string($qr)) {
                Cache::put('evolution.qr', $qr, now()->addMinutes(2));
            }

            return response('OK', 200);
        }

        $normalized = $normalizer->fromEvolution($payload);

        if ($normalized === null) {
            return response('OK', 200);
        }

        $messages = $normalized['entry'][0]['changes'][0]['value']['messages'] ?? [];
        $ids = array_map(fn ($m) => is_array($m) ? ($m['id'] ?? '') : '', is_array($messages) ? $messages : []);

        try {
            $stored = WebhookEvent::query()->create([
                'dedupe_key' => hash('sha256', 'evolution|'.$event.'|'.implode(',', $ids)),
                'payload' => $normalized,
            ]);
        } catch (UniqueConstraintViolationException) {
            return response('OK', 200);
        }

        try {
            $dispatcher->dispatch($stored->id);
        } catch (Throwable $exception) {
            report($exception);
        }

        return response('OK', 200);
    }
}
