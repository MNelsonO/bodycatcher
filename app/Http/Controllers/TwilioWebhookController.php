<?php

namespace App\Http\Controllers;

use App\WhatsApp\Services\InboundPayloadNormalizer;
use App\WhatsApp\WebhookDispatcher;
use App\WhatsApp\WebhookEvent;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

class TwilioWebhookController extends Controller
{
    public function store(
        Request $request,
        InboundPayloadNormalizer $normalizer,
        WebhookDispatcher $dispatcher,
    ): Response {
        $authToken = (string) config('whatsapp.twilio.auth_token');

        if ($authToken !== '' && ! $this->signatureValid($request, $authToken)) {
            return response('Invalid Twilio signature.', 403);
        }

        $normalized = $normalizer->fromTwilio($request->all());
        $raw = json_encode($normalized, JSON_THROW_ON_ERROR);

        try {
            $event = WebhookEvent::query()->create([
                'dedupe_key' => hash('sha256', $raw),
                'payload' => $normalized,
            ]);
        } catch (UniqueConstraintViolationException) {
            return response('OK', 200)->header('Content-Type', 'text/xml');
        }

        try {
            $dispatcher->dispatch($event->id);
        } catch (Throwable $exception) {
            report($exception);
        }

        // Empty TwiML: the desk sends replies through the API, not the webhook response.
        return response('<?xml version="1.0" encoding="UTF-8"?><Response></Response>', 200)
            ->header('Content-Type', 'text/xml');
    }

    private function signatureValid(Request $request, string $authToken): bool
    {
        $signature = (string) $request->header('X-Twilio-Signature', '');

        if ($signature === '') {
            return false;
        }

        $url = $request->fullUrl();
        $params = $request->post();
        ksort($params);
        $data = $url;

        foreach ($params as $key => $value) {
            $data .= $key.$value;
        }

        $expected = base64_encode(hash_hmac('sha1', $data, $authToken, true));

        return hash_equals($expected, $signature);
    }
}
