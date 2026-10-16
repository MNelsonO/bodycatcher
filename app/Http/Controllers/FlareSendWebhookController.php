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
        $secret = (string) config('whatsapp.flaresend.webhook_secret');
        $header = (string) $request->header('X-FlareSend-Secret', $request->header('Authorization', ''));

        if ($secret !== '' && ! $this->authorized($secret, $header)) {
            Log::warning('FlareSend webhook rejected: secret did not match.');

            return response('Invalid FlareSend secret.', 403);
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
