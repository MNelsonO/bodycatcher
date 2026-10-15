<?php

use App\Contacts\ConsentStatus;
use App\Contacts\Contact;
use App\Conversations\Conversation;
use App\Conversations\Message;
use App\Conversations\MessageAttachment;
use App\Conversations\MessageOrigin;
use App\Conversations\MessageStatus;
use App\Conversations\MessageStatusEvent;
use App\Leads\Lead;
use App\Models\User;
use App\WhatsApp\Services\InboundWhatsAppProcessor;
use App\WhatsApp\Services\SendWhatsAppMessage;
use App\WhatsApp\WebhookDispatcher;
use App\WhatsApp\WebhookEvent;
use Illuminate\Support\Facades\Http;

it('verifies the webhook when the token matches', function () {
    whatsappConfig();

    $this->get('/webhooks/whatsapp?'.http_build_query([
        'hub.mode' => 'subscribe',
        'hub.verify_token' => 'verify-me',
        'hub.challenge' => '12345',
    ]))->assertOk()->assertSee('12345');
});

it('rejects a bad verify token', function () {
    whatsappConfig();

    $this->get('/webhooks/whatsapp?'.http_build_query([
        'hub.mode' => 'subscribe',
        'hub.verify_token' => 'nope',
        'hub.challenge' => '12345',
    ]))->assertForbidden();
});

it('rejects a bad signature and stores nothing', function () {
    whatsappConfig();
    crmDesk();

    postWhatsAppWebhook(
        whatsappTextPayload('254722100201', 'Hello from Westlands', 'wamid.in.1'),
        'sha256=deadbeef',
    )->assertForbidden();

    expect(WebhookEvent::query()->count())->toBe(0)
        ->and(Contact::query()->count())->toBe(0)
        ->and(Lead::query()->count())->toBe(0)
        ->and(Message::query()->count())->toBe(0);
});

it('creates a contact, lead, conversation, and message from inbound text', function () {
    whatsappConfig();
    crmDesk();

    postWhatsAppWebhook(whatsappTextPayload('254722100201', 'Hello from Westlands', 'wamid.in.1'))
        ->assertOk();

    $contact = Contact::query()->first();
    $lead = Lead::query()->with('currentStage')->first();

    expect($contact)->not->toBeNull()
        ->and($contact->primary_phone)->toBe('+254722100201')
        ->and($contact->full_name)->toBe('Amina Wanjiku')
        ->and(Lead::query()->count())->toBe(1)
        ->and(Conversation::query()->count())->toBe(1)
        ->and(Message::query()->count())->toBe(1)
        ->and($lead->source)->toBe('whatsapp')
        ->and($lead->currentStage->name)->toBe('New')
        ->and($lead->closed_at)->toBeNull()
        ->and(Message::query()->first()?->body)->toBe('Hello from Westlands')
        ->and(Message::query()->first()?->direction->value)->toBe('inbound');
});

it('does not duplicate a second delivery of the same event', function () {
    whatsappConfig();
    crmDesk();
    $payload = whatsappTextPayload('254722100201', 'Hello from Westlands', 'wamid.in.1');

    postWhatsAppWebhook($payload)->assertOk();
    postWhatsAppWebhook($payload)->assertOk();

    expect(WebhookEvent::query()->count())->toBe(1)
        ->and(Contact::query()->count())->toBe(1)
        ->and(Lead::query()->count())->toBe(1)
        ->and(Message::query()->count())->toBe(1);
});

it('attaches an inbound message to the open lead for an existing phone', function () {
    $lead = whatsappOpenLead();

    postWhatsAppWebhook(whatsappTextPayload('254722100201', 'Still interested', 'wamid.in.2'))
        ->assertOk();

    expect(Lead::query()->count())->toBe(1)
        ->and(Contact::query()->count())->toBe(1)
        ->and(Message::query()->where('provider_message_id', 'wamid.in.2')->first()?->conversation?->lead_id)->toBe($lead->id);
});

it('opens a new lead when the only existing lead is closed', function () {
    $lead = whatsappOpenLead();
    $lost = $lead->pipeline->stages()->where('kind', 'lost')->firstOrFail();
    $lead->current_stage_id = $lost->id;
    $lead->closed_at = now();
    $lead->save();

    postWhatsAppWebhook(whatsappTextPayload('254722100201', 'New season', 'wamid.in.3'))
        ->assertOk();

    expect(Lead::query()->count())->toBe(2)
        ->and(Lead::query()->open()->count())->toBe(1)
        ->and($lead->fresh()?->closed_at)->not->toBeNull();
});

it('records delivered and failed status events without treating a send as delivered', function () {
    $lead = whatsappOpenLead();
    whatsappInbound($lead);

    $outcome = app(SendWhatsAppMessage::class)->send(
        $lead->fresh() ?? $lead,
        'Saturday is fine.',
        User::query()->firstOrFail(),
        MessageOrigin::Manual,
        'send-1',
    );

    expect($outcome->message->status)->toBe(MessageStatus::Sent)
        ->and($outcome->message->provider_message_id)->not->toBeNull();

    $providerId = (string) $outcome->message->provider_message_id;

    postWhatsAppWebhook(whatsappStatusPayload($providerId, 'delivered', now()->timestamp))->assertOk();
    postWhatsAppWebhook(whatsappStatusPayload($providerId, 'delivered', now()->timestamp))->assertOk();

    expect($outcome->message->fresh()?->status)->toBe(MessageStatus::Delivered)
        ->and(MessageStatusEvent::query()->count())->toBe(1);

    postWhatsAppWebhook(whatsappStatusPayload($providerId, 'failed', now()->addMinute()->timestamp))->assertOk();

    expect($outcome->message->fresh()?->status)->toBe(MessageStatus::Failed)
        ->and(MessageStatusEvent::query()->count())->toBe(2);
});

it('opts out on the exact keyword stop', function () {
    whatsappConfig();
    crmDesk();

    postWhatsAppWebhook(whatsappTextPayload('254722100201', 'STOP', 'wamid.stop.1'))->assertOk();

    expect(Contact::query()->first()?->consent_status)->toBe(ConsentStatus::OptedOut)
        ->and(Contact::query()->first()?->opted_out_at)->not->toBeNull()
        ->and(Message::query()->count())->toBe(1);
});

it('stores attachment metadata and does not download media', function () {
    whatsappConfig();
    crmDesk();
    Http::fake();

    $payload = whatsappTextPayload('254722100201', 'ignored', 'wamid.img.1');
    $payload['entry'][0]['changes'][0]['value']['messages'][0] = [
        'from' => '254722100201',
        'id' => 'wamid.img.1',
        'timestamp' => (string) now()->timestamp,
        'type' => 'image',
        'image' => [
            'id' => 'media-9',
            'mime_type' => 'image/jpeg',
            'caption' => 'Shop front on Ngong Road',
        ],
    ];

    postWhatsAppWebhook($payload)->assertOk();

    $attachment = MessageAttachment::query()->first();

    expect($attachment)->not->toBeNull()
        ->and($attachment->provider_media_id)->toBe('media-9')
        ->and($attachment->mime_type)->toBe('image/jpeg')
        ->and($attachment->caption)->toBe('Shop front on Ngong Road')
        ->and(Message::query()->first()?->body)->toBe('Shop front on Ngong Road');

    Http::assertNothingSent();
});

it('keeps the webhook row when the queue push fails', function () {
    whatsappConfig();
    crmDesk();

    $this->app->instance(WebhookDispatcher::class, new class implements WebhookDispatcher
    {
        public function dispatch(int $eventId): void
        {
            throw new RuntimeException('redis down');
        }
    });

    postWhatsAppWebhook(whatsappTextPayload('254722100201', 'Hello from Westlands', 'wamid.in.queue'))
        ->assertOk();

    $event = WebhookEvent::query()->first();

    expect($event)->not->toBeNull()
        ->and($event->processed_at)->toBeNull()
        ->and(Message::query()->count())->toBe(0);

    app(InboundWhatsAppProcessor::class)->process($event->id);

    expect(Message::query()->count())->toBe(1)
        ->and($event->fresh()?->processed_at)->not->toBeNull();
});
