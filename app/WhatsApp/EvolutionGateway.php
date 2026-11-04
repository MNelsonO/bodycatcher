<?php

namespace App\WhatsApp;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Talks to the self-hosted Evolution API: one instance for the desk's
 * WhatsApp number, linked by scanning a QR code.
 */
class EvolutionGateway
{
    public const EVENTS = ['MESSAGES_UPSERT', 'MESSAGES_SET', 'SEND_MESSAGE', 'CONNECTION_UPDATE', 'QRCODE_UPDATED'];

    public function configured(): bool
    {
        return filled(config('whatsapp.evolution.api_key')) && filled(config('whatsapp.evolution.base_url'));
    }

    public function instance(): string
    {
        return (string) config('whatsapp.evolution.instance', 'kemet');
    }

    public function webhookUrl(): string
    {
        return url('/webhooks/evolution/'.config('whatsapp.evolution.webhook_token'));
    }

    /**
     * open = linked and working; connecting = waiting for a QR scan;
     * close = not linked; missing = no instance yet; offline = gateway down.
     */
    public function state(): string
    {
        if (! $this->configured()) {
            return 'unconfigured';
        }

        try {
            $response = $this->http()->get('/instance/connectionState/'.$this->instance());
        } catch (ConnectionException) {
            return 'offline';
        }

        if ($response->status() === 404) {
            return 'missing';
        }

        $state = $response->json('instance.state') ?? $response->json('state');

        return is_string($state) && $state !== '' ? $state : 'close';
    }

    /**
     * Creates the instance when needed (with the desk webhook), then returns
     * a fresh QR code as a data URI, or null when already linked.
     *
     * @return array{qr: ?string, pairing_code: ?string, state: string}
     */
    public function connect(): array
    {
        $state = $this->state();

        if ($state === 'open') {
            return ['qr' => null, 'pairing_code' => null, 'state' => 'open'];
        }

        if ($state === 'missing') {
            $created = $this->http()->post('/instance/create', [
                'instanceName' => $this->instance(),
                'integration' => 'WHATSAPP-BAILEYS',
                'qrcode' => true,
                'syncFullHistory' => true,
                'groupsIgnore' => true,
                'readMessages' => false,
                'webhook' => [
                    'url' => $this->webhookUrl(),
                    'byEvents' => false,
                    'base64' => false,
                    'events' => self::EVENTS,
                ],
            ]);

            $qr = $this->qrFrom($created);

            if ($qr !== null) {
                return ['qr' => $qr, 'pairing_code' => $this->pairingFrom($created), 'state' => 'connecting'];
            }
        } else {
            $this->ensureWebhook();
        }

        $response = $this->http()->get('/instance/connect/'.$this->instance());

        return ['qr' => $this->qrFrom($response), 'pairing_code' => $this->pairingFrom($response), 'state' => $this->state()];
    }

    public function ensureWebhook(): void
    {
        $this->http()->post('/webhook/set/'.$this->instance(), [
            'webhook' => [
                'enabled' => true,
                'url' => $this->webhookUrl(),
                'byEvents' => false,
                'base64' => false,
                'events' => self::EVENTS,
            ],
        ]);
    }

    public function logout(): void
    {
        $this->http()->delete('/instance/logout/'.$this->instance());
    }

    public function sendText(string $number, string $text): Response
    {
        return $this->http()->timeout(25)->post('/message/sendText/'.$this->instance(), [
            'number' => $number,
            'text' => $text,
        ]);
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('whatsapp.evolution.base_url'), '/'))
            ->withHeaders(['apikey' => (string) config('whatsapp.evolution.api_key')])
            ->acceptJson()
            ->timeout(15);
    }

    private function qrFrom(Response $response): ?string
    {
        foreach (['qrcode.base64', 'base64', 'qrcode.code'] as $path) {
            $value = $response->json($path);

            if (is_string($value) && str_starts_with($value, 'data:image')) {
                return $value;
            }
        }

        return null;
    }

    private function pairingFrom(Response $response): ?string
    {
        $value = $response->json('pairingCode') ?? $response->json('qrcode.pairingCode');

        return is_string($value) && $value !== '' ? $value : null;
    }
}
