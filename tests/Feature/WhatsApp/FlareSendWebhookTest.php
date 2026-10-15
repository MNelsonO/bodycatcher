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
