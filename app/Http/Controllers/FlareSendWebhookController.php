<?php

namespace App\Http\Controllers;

use App\WhatsApp\Services\InboundPayloadNormalizer;
use App\WhatsApp\WebhookDispatcher;
use App\WhatsApp\WebhookEvent;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

class FlareSendWebhookController extends Controller
{
    public function store(
        Request $request,
        InboundPayloadNormalizer $normalizer,
        WebhookDispatcher $dispatcher,
    ): Response {
        Log::info('FlareSend hit', [
            'path' => $request->path(),
            'ip' => $request->ip(),
            'body' => mb_substr($request->getContent(), 0, 500),
        ]);

        $secret = (string) config('whatsapp.flaresend.webhook_secret');
        $header = (string) $request->header(
            'X-FlareSend-Secret',
            (string) $request->header('Authorization', ''),
        );

        // FlareSend's WhatsApp docs do not document a secret header. Only reject
        // when a secret is configured AND the request sends a wrong one.
        if ($secret !== '' && $header !== '' && ! $this->authorized($secret, $header)) {
            Log::warning('FlareSend webhook rejected: secret did not match.');

            return response('Invalid FlareSend secret.', 403);
        }

        if ($secret !== '' && $header === '') {
            Log::info('FlareSend webhook accepted without secret header.');
        }

        $decoded = $request->all();

        if ($decoded === []) {
            $raw = $request->getContent();
            $json = json_decode($raw, true);
            $decoded = is_array($json) ? $json : [];
        }

        if ($decoded === []) {
            return response('Invalid payload.', 400);
        }

        $normalized = $normalizer->fromFlareSend($decoded);
        $raw = json_encode($normalized, JSON_THROW_ON_ERROR);

        try {
            $event = WebhookEvent::query()->create([
                'dedupe_key' => hash('sha256', $raw),
                'payload' => $normalized,
            ]);
        } catch (UniqueConstraintViolationException) {
            return response('OK', 200);
        }

        try {
            $dispatcher->dispatch($event->id);
        } catch (Throwable $exception) {
            report($exception);
        }

        return response('OK', 200);
    }

    private function authorized(string $secret, string $header): bool
    {
        if ($header === $secret) {
            return true;
        }

        if (str_starts_with($header, 'Bearer ')) {
            return hash_equals($secret, substr($header, 7));
        }

        return hash_equals($secret, $header);
    }
}
