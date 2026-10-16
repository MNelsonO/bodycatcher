<?php

use App\Contacts\Contact;
use App\Conversations\Message;
use App\Leads\Lead;
use App\WhatsApp\WebhookEvent;

it('accepts a FlareSend inbound message and opens a lead', function () {
    config([
        'whatsapp.driver' => 'flaresend',
        'whatsapp.flaresend.api_key' => 'fs-key',
        'whatsapp.flaresend.webhook_secret' => 'fs-secret',
    ]);
    crmDesk();

    $payload = [
        'event' => 'message_received',
        'instance_id' => 'inst-1',
        'data' => [
            'from' => '254722100201',
            'name' => 'Amina Wanjiku',
            'message' => [
                'type' => 'text',
                'text' => 'Hello from FlareSend',
            ],
            'timestamp' => now()->timestamp,
            'id' => 'fs-msg-1',
        ],
    ];

    $this->postJson('/webhooks/flaresend', $payload, [
        'X-FlareSend-Secret' => 'fs-secret',
    ])->assertOk();

    expect(WebhookEvent::query()->count())->toBe(1)
        ->and(Contact::query()->first()?->primary_phone)->toBe('+254722100201')
        ->and(Lead::query()->count())->toBe(1)
        ->and(Message::query()->first()?->body)->toBe('Hello from FlareSend');
});

it('accepts the documented FlareSend message_received payload', function () {
    config([
        'whatsapp.driver' => 'flaresend',
        'whatsapp.flaresend.api_key' => 'fs-key',
        'whatsapp.flaresend.webhook_secret' => 'fs-secret',
    ]);
    crmDesk();

    $this->postJson('/webhooks/flaresend', [
        'event' => 'message_received',
        'instanceId' => '32208d70-8f21-4af5-a94c-59432f7cfe14',
        'data' => [
            'from' => '254708920430@s.whatsapp.net',
            'message' => [
                'extendedTextMessage' => [
                    'text' => 'niaje',
                ],
            ],
            'timestamp' => 1759697260,
            'id' => '3F26D637831DD7A0C9B9',
        ],
        'timestamp' => '2025-10-05T20:47:19.376Z',
    ], [
        'X-FlareSend-Secret' => 'fs-secret',
    ])->assertOk();

    expect(Contact::query()->first()?->primary_phone)->toBe('+254708920430')
        ->and(Message::query()->first()?->body)->toBe('niaje');
});

it('rejects a FlareSend webhook with a bad secret', function () {
    config([
        'whatsapp.flaresend.webhook_secret' => 'fs-secret',
    ]);

    $this->postJson('/webhooks/flaresend', [
        'data' => ['from' => '254722100201', 'message' => ['type' => 'text', 'text' => 'Hi'], 'id' => 'x'],
    ], [
        'X-FlareSend-Secret' => 'wrong',
    ])->assertForbidden();

    expect(WebhookEvent::query()->count())->toBe(0);
});
