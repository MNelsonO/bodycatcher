<?php

use App\Contacts\Contact;
use App\Conversations\Conversation;
use App\Conversations\Message;
use App\Leads\Lead;
use App\WhatsApp\EvolutionWhatsAppClient;
use App\WhatsApp\WhatsAppClient;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'whatsapp.evolution.base_url' => 'http://127.0.0.1:8080',
        'whatsapp.evolution.api_key' => 'test-key',
        'whatsapp.evolution.instance' => 'kemet',
        'whatsapp.evolution.webhook_token' => 'secret-token',
    ]);
});

function evolutionUpsert(array $data, string $event = 'messages.upsert'): array
{
    return ['event' => $event, 'instance' => 'kemet', 'data' => $data, 'date_time' => now()->toIso8601String()];
}

it('rejects webhooks with the wrong token', function () {
    $this->postJson('/webhooks/evolution/wrong', evolutionUpsert([]))->assertNotFound();
});

it('stores a client message and the owner\'s phone reply in the same chat', function () {
    $lead = whatsappOpenLead();

    $this->postJson('/webhooks/evolution/secret-token', evolutionUpsert([
        'key' => ['remoteJid' => '254722100201@s.whatsapp.net', 'fromMe' => false, 'id' => 'EVO-IN-1'],
        'pushName' => 'Amina W',
        'message' => ['conversation' => 'Bei ni ngapi?'],
        'messageType' => 'conversation',
        'messageTimestamp' => now()->subMinutes(3)->timestamp,
    ]))->assertOk();

    $this->postJson('/webhooks/evolution/secret-token', evolutionUpsert([
        'key' => ['remoteJid' => '254722100201@s.whatsapp.net', 'fromMe' => true, 'id' => 'EVO-OUT-1'],
        'pushName' => 'Nelson',
        'message' => ['extendedTextMessage' => ['text' => 'KES 4,500 per month.']],
        'messageType' => 'extendedTextMessage',
        'messageTimestamp' => now()->subMinutes(2)->timestamp,
    ]))->assertOk();

    $messages = Message::query()->whereHas('conversation', fn ($q) => $q->where('lead_id', $lead->id))->orderBy('sent_at')->get();

    expect($messages->pluck('direction')->map->value->all())->toBe(['inbound', 'outbound'])
        ->and($messages[1]->body)->toBe('KES 4,500 per month.')
        ->and(Contact::query()->count())->toBe(1)
        ->and($lead->contact->refresh()->full_name)->toBe('Amina Wanjiku');
});

it('resolves privacy ids to the phone number and ignores groups and statuses', function () {
    $lead = whatsappOpenLead();

    $this->postJson('/webhooks/evolution/secret-token', evolutionUpsert([
        'key' => ['remoteJid' => '190391773040704@lid', 'remoteJidAlt' => '254722100201@s.whatsapp.net', 'fromMe' => false, 'id' => 'EVO-LID'],
        'message' => ['conversation' => 'From a lid'],
        'messageTimestamp' => now()->timestamp,
    ]))->assertOk();

    foreach (['120363000000000000@g.us', 'status@broadcast'] as $jid) {
        $this->postJson('/webhooks/evolution/secret-token', evolutionUpsert([
            'key' => ['remoteJid' => $jid, 'fromMe' => false, 'id' => 'EVO-'.md5($jid)],
            'message' => ['conversation' => 'ignore me'],
            'messageTimestamp' => now()->timestamp,
        ]))->assertOk();
    }

    expect(Message::query()->where('provider_message_id', 'EVO-LID')->value('conversation_id'))->toBe($lead->conversation->id)
        ->and(Message::query()->where('body', 'ignore me')->exists())->toBeFalse();
});

it('fills history only for people already in the desk, without triggering anything', function () {
    $lead = whatsappOpenLead();
    $before = Lead::query()->count();

    $this->postJson('/webhooks/evolution/secret-token', evolutionUpsert(['messages' => [
        [
            'key' => ['remoteJid' => '254722100201@s.whatsapp.net', 'fromMe' => true, 'id' => 'H1'],
            'message' => ['conversation' => 'Old reply from my phone'],
            'messageTimestamp' => now()->subDays(3)->timestamp,
        ],
        [
            'key' => ['remoteJid' => '254700999888@s.whatsapp.net', 'fromMe' => false, 'id' => 'H2'],
            'message' => ['conversation' => 'Mum: dinner at 7'],
            'messageTimestamp' => now()->subDays(2)->timestamp,
        ],
    ]], 'messages.set'))->assertOk();

    expect(Message::query()->where('provider_message_id', 'H1')->value('direction')?->value)->toBe('outbound')
        ->and(Message::query()->where('provider_message_id', 'H2')->exists())->toBeFalse()
        ->and(Lead::query()->count())->toBe($before)
        ->and($lead->conversation->refresh()->last_read_at)->not->toBeNull();
});

it('does not store a desk send twice when the gateway echoes it back', function () {
    $lead = whatsappOpenLead();
    whatsappInbound($lead);
    config(['whatsapp.driver' => 'evolution']);
    app()->instance(WhatsAppClient::class, app(EvolutionWhatsAppClient::class));

    Http::fake(['127.0.0.1:8080/message/sendText/kemet' => Http::response([
        'key' => ['remoteJid' => '254722100201@s.whatsapp.net', 'fromMe' => true, 'id' => 'WA-REAL-ID'],
        'status' => 'PENDING',
    ], 201)]);

    $this->post(route('leads.whatsapp.send', $lead), ['body' => 'Sent from the desk', 'idempotency_key' => 'k-1'])->assertRedirect();

    Http::assertSent(fn ($request) => $request->hasHeader('apikey', 'test-key') && $request['number'] === '254722100201');

    $this->postJson('/webhooks/evolution/secret-token', evolutionUpsert([
        'key' => ['remoteJid' => '254722100201@s.whatsapp.net', 'fromMe' => true, 'id' => 'WA-REAL-ID'],
        'message' => ['conversation' => 'Sent from the desk'],
        'messageTimestamp' => now()->timestamp,
    ]))->assertOk();

    expect(Message::query()->where('body', 'Sent from the desk')->count())->toBe(1)
        ->and(Message::query()->where('body', 'Sent from the desk')->value('provider_message_id'))->toBe('WA-REAL-ID');
});

it('reports the gateway as not installed when no key is set', function () {
    config(['whatsapp.evolution.api_key' => '']);
    $this->actingAs(crmSalesUser());

    $this->getJson('/whatsapp/gateway')->assertOk()->assertJsonPath('state', 'unconfigured');
});

it('matches an unresolved privacy id to the contact created under that id', function () {
    whatsappOpenLead();
    $lidContact = Contact::factory()->create(['full_name' => 'Privacy Person', 'primary_phone' => '+190391773040704']);
    $lidLead = Lead::query()->create([
        'contact_id' => $lidContact->id,
        'product_id' => Lead::query()->value('product_id'),
        'pipeline_id' => Lead::query()->value('pipeline_id'),
        'current_stage_id' => Lead::query()->value('current_stage_id'),
        'decision_maker_status' => 'unknown',
    ]);
    Conversation::query()->create(['lead_id' => $lidLead->id, 'contact_id' => $lidContact->id]);

    $this->postJson('/webhooks/evolution/secret-token', evolutionUpsert(['messages' => [[
        'key' => ['remoteJid' => '190391773040704@lid', 'fromMe' => true, 'id' => 'LID-H1'],
        'message' => ['conversation' => 'Reply to a privacy id'],
        'messageTimestamp' => now()->subDay()->timestamp,
    ]]], 'messages.set'))->assertOk();

    expect(Message::query()->where('provider_message_id', 'LID-H1')->value('conversation_id'))->toBe($lidLead->conversation->id);
});
