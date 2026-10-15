<?php

namespace App\Http\Controllers;

use App\WhatsApp\WebhookDispatcher;
use App\WhatsApp\WebhookEvent;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

class WhatsAppWebhookController extends Controller
{
    public function verify(Request $request): Response
    {
        $mode = $request->query('hub_mode', $request->query('hub.mode'));
        $token = $request->query('hub_verify_token', $request->query('hub.verify_token'));
        $challenge = $request->query('hub_challenge', $request->query('hub.challenge'));
        $expected = config('whatsapp.verify_token');

        if (
            $mode === 'subscribe'
            && is_string($expected)
            && $expected !== ''
            && is_string($token)
            && is_string($challenge)
            && hash_equals($expected, $token)
        ) {
            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        return response('Verification failed.', 403);
    }

    public function store(Request $request, WebhookDispatcher $dispatcher): Response
    {
        $raw = $request->getContent();
        $secret = config('whatsapp.app_secret');
        $header = (string) $request->header('X-Hub-Signature-256', '');

        if (! is_string($secret) || $secret === '' || ! $this->signatureValid($raw, $secret, $header)) {
            return response('Invalid signature.', 403);
        }

        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            return response('Invalid payload.', 400);
        }

        try {
            $event = WebhookEvent::query()->create([
                'dedupe_key' => hash('sha256', $raw),
                'payload' => $decoded,
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

    private function signatureValid(string $raw, string $secret, string $header): bool
    {
        if (! str_starts_with($header, 'sha256=')) {
            return false;
        }

        $expected = 'sha256='.hash_hmac('sha256', $raw, $secret);

        return hash_equals($expected, $header);
    }
}
