<?php

use App\Leads\Interest\InterestClassifier;
use App\Leads\Lead;

beforeEach(function () {
    config([
        'whatsapp.evolution.base_url' => 'http://127.0.0.1:8080',
        'whatsapp.evolution.api_key' => 'k',
        'whatsapp.evolution.webhook_token' => 'secret-token',
    ]);
});

function adMessage(string $id, string $text, array $ad): array
{
    return [
        'event' => 'messages.upsert',
        'data' => [
            'key' => ['remoteJid' => '254722100201@s.whatsapp.net', 'fromMe' => false, 'id' => $id],
            'message' => [
                'extendedTextMessage' => [
                    'text' => $text,
                    'contextInfo' => [
                        'entryPointConversionSource' => 'ctwa_ad',
                        'externalAdReply' => $ad,
                    ],
                ],
            ],
            'messageTimestamp' => now()->timestamp,
        ],
    ];
}

it('records the Click-to-WhatsApp ad and sorts the chat under that system', function () {
    $lead = whatsappOpenLead();

    $this->postJson('/webhooks/evolution/secret-token', adMessage('AD-1', 'Hello, I want more info', [
        'title' => 'Run your laundry from your phone',
        'body' => 'Track every order, ironing and pickup with Kemet Laundry.',
        'sourceId' => '120210000000001',
        'sourceUrl' => 'https://fb.me/abc',
    ]))->assertOk();

    $lead->refresh();

    expect($lead->ad_id)->toBe('120210000000001')
        ->and($lead->ad_title)->toBe('Run your laundry from your phone')
        ->and($lead->interest_system)->toBe('laundry')
        ->and($lead->interest_source)->toBe('ad');

    $this->get(route('inbox.show', $lead->conversation))
        ->assertInertia(fn ($page) => $page
            ->where('thread.lead.interest.key', 'laundry')
            ->where('thread.lead.ad.title', 'Run your laundry from your phone'));

    $this->get(route('ads.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('ads/index')
            ->where('report.ads.0.title', 'Run your laundry from your phone')
            ->where('report.ads.0.leads', 1));
});

it('leaves a vague chat unclassified so the desk asks first', function () {
    $lead = whatsappOpenLead();

    $this->postJson('/webhooks/evolution/secret-token', [
        'event' => 'messages.upsert',
        'data' => [
            'key' => ['remoteJid' => '254722100201@s.whatsapp.net', 'fromMe' => false, 'id' => 'VAGUE-1'],
            'message' => ['conversation' => 'Hi, I saw your post. How much?'],
            'messageTimestamp' => now()->timestamp,
        ],
    ])->assertOk();

    expect($lead->refresh()->interest_system)->toBeNull();

    $this->get(route('inbox.show', $lead->conversation))
        ->assertInertia(fn ($page) => $page->where('thread.lead.interest.key', null));
});

it('lets the desk set the system by hand and keeps it over later guesses', function () {
    $lead = whatsappOpenLead();

    $this->post(route('inbox.interest', $lead->conversation), ['system' => 'hotel'])->assertRedirect();

    expect($lead->refresh()->interest_system)->toBe('hotel')
        ->and($lead->interest_source)->toBe('manual');

    app(InterestClassifier::class)->recordAd($lead, ['id' => 'x', 'title' => 'Car wash software', 'body' => 'car wash', 'url' => null, 'source' => null]);

    expect(Lead::query()->find($lead->id)?->interest_system)->toBe('hotel');
});

it('does not guess when two systems tie', function () {
    expect(app(InterestClassifier::class)->fromText('laundry or car wash?'))->toBeNull();
});

it('classifies existing chats with the command', function () {
    $lead = whatsappOpenLead();
    $lead->conversation->messages()->create([
        'direction' => 'inbound',
        'channel' => 'whatsapp',
        'origin' => 'manual',
        'status' => 'received',
        'body' => 'Do you have a system for my car wash business?',
        'sent_at' => now(),
    ]);

    $this->artisan('leads:classify-interest')->assertSuccessful();

    expect($lead->refresh()->interest_system)->toBe('carwash')
        ->and($lead->interest_source)->toBe('chat');
});
