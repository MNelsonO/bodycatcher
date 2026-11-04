<?php

namespace App\Http\Controllers;

use App\WhatsApp\Labels\LabelSync;
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
        LabelSync $labels,
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

        if ($event === 'labels.edit' || $event === 'labels.association') {
            Cache::put('evolution.last_label_payload', mb_substr((string) json_encode($payload), 0, 4000), now()->addDay());
            $this->labels($event, $payload, $labels);

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

    /**
     * @param  array<string, mixed>  $payload
     */
    private function labels(string $event, array $payload, LabelSync $labels): void
    {
        $data = $payload['data'] ?? null;
        $items = is_array($data) && array_is_list($data) ? $data : [$data];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            if ($event === 'labels.edit') {
                $labels->upsertLabel($item);

                continue;
            }

            $association = is_array($item['association'] ?? null) ? $item['association'] : $item;
            $chat = (string) ($association['chatId'] ?? $association['chat_id'] ?? $association['remoteJid'] ?? '');
            $labelId = (string) ($association['labelId'] ?? $association['label_id'] ?? $item['labelId'] ?? '');
            $type = mb_strtolower((string) ($item['type'] ?? $association['type'] ?? 'add'));

            $labels->associate($chat, $labelId, $type !== 'remove');
        }
    }
}
