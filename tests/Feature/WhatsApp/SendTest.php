<?php

use App\Contacts\ConsentStatus;
use App\Conversations\Message;
use App\Conversations\MessageOrigin;
use App\Conversations\MessageStatus;
use App\Models\User;
use App\WhatsApp\CloudWhatsAppClient;
use App\WhatsApp\DeskSetting;
use App\WhatsApp\FakeWhatsAppClient;
use App\WhatsApp\Jobs\RetryWhatsAppSend;
use App\WhatsApp\SendAttempt;
use App\WhatsApp\Services\SendWhatsAppMessage;
use App\WhatsApp\WhatsAppSendResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

it('blocks outbound when the contact has opted out', function () {
    $lead = whatsappOpenLead();
    whatsappInbound($lead);
    $lead->contact->update([
        'consent_status' => ConsentStatus::OptedOut,
        'opted_out_at' => now(),
    ]);

    expect(fn () => app(SendWhatsAppMessage::class)->send(
        $lead->fresh() ?? $lead,
        'Are you still looking?',
        User::query()->firstOrFail(),
        MessageOrigin::Manual,
        'opt-out-1',
    ))->toThrow(ValidationException::class);

    expect(app(FakeWhatsAppClient::class)->sends)->toBe(0)
        ->and(Message::query()->where('status', MessageStatus::Refused)->count())->toBe(1)
        ->and(Message::query()->where('status', MessageStatus::Queued)->count())->toBe(0);
});

it('refuses free-form text outside the 24-hour window on Meta without a template', function () {
    config(['whatsapp.driver' => 'meta', 'whatsapp.template.name' => '']);
    $lead = whatsappOpenLead();
    whatsappInbound($lead, now()->subHours(25));

    expect(fn () => app(SendWhatsAppMessage::class)->send(
        $lead->fresh() ?? $lead,
        'Checking in.',
        User::query()->firstOrFail(),
        MessageOrigin::Manual,
        'window-1',
    ))->toThrow(ValidationException::class);

    $refused = Message::query()->where('status', MessageStatus::Refused)->first();

    expect(app(FakeWhatsAppClient::class)->sends)->toBe(0)
        ->and($refused)->not->toBeNull()
        ->and($refused->refusal_reason)->toContain('WHATSAPP_TEMPLATE_NAME')
        ->and(Message::query()->where('status', MessageStatus::Queued)->count())->toBe(0);
});

it('sends a Meta template when the 24-hour window is closed', function () {
    config([
        'whatsapp.driver' => 'meta',
        'whatsapp.template.name' => 'desk_follow_up',
        'whatsapp.template.language' => 'en',
    ]);
    $lead = whatsappOpenLead();
    whatsappInbound($lead, now()->subHours(25));

    $outcome = app(SendWhatsAppMessage::class)->send(
        $lead->fresh() ?? $lead,
        'Checking in about the laundry system.',
        User::query()->firstOrFail(),
        MessageOrigin::Manual,
        'window-template-1',
    );

    expect($outcome->message->status)->toBe(MessageStatus::Sent)
        ->and(app(FakeWhatsAppClient::class)->sends)->toBe(1)
        ->and(app(FakeWhatsAppClient::class)->calls[0]['mode'])->toBe('template');
});

it('allows free-form FlareSend text outside any 24-hour window', function () {
    config([
        'whatsapp.driver' => 'flaresend',
        'whatsapp.flaresend.api_key' => 'test-flare-key',
    ]);
    $lead = whatsappOpenLead();
    whatsappInbound($lead, now()->subHours(48));

    $outcome = app(SendWhatsAppMessage::class)->send(
        $lead->fresh() ?? $lead,
        'Following up on POS.',
        User::query()->firstOrFail(),
        MessageOrigin::Manual,
        'flare-window-1',
    );

    expect($outcome->message->status)->toBe(MessageStatus::Sent)
        ->and(app(FakeWhatsAppClient::class)->sends)->toBe(1)
        ->and(app(FakeWhatsAppClient::class)->calls[0]['mode'])->toBe('text');
});

it('stores the provider id when a manual send is inside the window', function () {
    $lead = whatsappOpenLead();
    whatsappInbound($lead);

    $outcome = app(SendWhatsAppMessage::class)->send(
        $lead->fresh() ?? $lead,
        'Saturday works for a visit.',
        User::query()->firstOrFail(),
        MessageOrigin::Manual,
        'manual-1',
    );

    expect($outcome->duplicate)->toBeFalse()
        ->and($outcome->message->status)->toBe(MessageStatus::Sent)
        ->and($outcome->message->provider_message_id)->toStartWith('wamid.fake.')
        ->and($outcome->message->provider_response)->not->toBeNull()
        ->and(app(FakeWhatsAppClient::class)->sends)->toBe(1);
});

it('refuses an automatic send on a paused lead and still allows a manual send', function () {
    $lead = whatsappOpenLead();
    whatsappInbound($lead);
    $lead->automation_paused = true;
    $lead->save();
    $user = User::query()->firstOrFail();
    $sender = app(SendWhatsAppMessage::class);

    expect(fn () => $sender->send($lead->fresh() ?? $lead, 'Auto note', $user, MessageOrigin::Automatic, 'pause-auto'))
        ->toThrow(ValidationException::class);

    expect(app(FakeWhatsAppClient::class)->sends)->toBe(0);

    $manual = $sender->send($lead->fresh() ?? $lead, 'I will call you.', $user, MessageOrigin::Manual, 'pause-manual');

    expect($manual->message->status)->toBe(MessageStatus::Sent)
        ->and(app(FakeWhatsAppClient::class)->sends)->toBe(1);
});

it('refuses an automatic send while the desk pause is on and still allows a manual send', function () {
    $lead = whatsappOpenLead();
    whatsappInbound($lead);
    DeskSetting::current()->update(['automatic_sends_paused' => true]);
    $user = User::query()->firstOrFail();
    $sender = app(SendWhatsAppMessage::class);

    expect(fn () => $sender->send($lead->fresh() ?? $lead, 'Auto note', $user, MessageOrigin::Automatic, 'global-auto'))
        ->toThrow(ValidationException::class);

    expect(app(FakeWhatsAppClient::class)->sends)->toBe(0);

    $manual = $sender->send($lead->fresh() ?? $lead, 'I will call you.', $user, MessageOrigin::Manual, 'global-manual');

    expect($manual->message->status)->toBe(MessageStatus::Sent)
        ->and(app(FakeWhatsAppClient::class)->sends)->toBe(1);
});

it('marks a timed-out send unknown and does not retry it', function () {
    $lead = whatsappOpenLead();
    whatsappInbound($lead);
    app(FakeWhatsAppClient::class)->push(WhatsAppSendResult::unknown(['error' => 'cURL error 28: Operation timed out']));

    $outcome = app(SendWhatsAppMessage::class)->send(
        $lead->fresh() ?? $lead,
        'Did this arrive?',
        User::query()->firstOrFail(),
        MessageOrigin::Manual,
        'timeout-1',
    );

    expect($outcome->message->status)->toBe(MessageStatus::Unknown)
        ->and($outcome->message->retryable)->toBeFalse()
        ->and(app(FakeWhatsAppClient::class)->sends)->toBe(1);

    RetryWhatsAppSend::dispatchSync($outcome->message->id);

    expect(app(FakeWhatsAppClient::class)->sends)->toBe(1)
        ->and($outcome->message->fresh()?->status)->toBe(MessageStatus::Unknown);
});

it('does not send the same idempotency key twice', function () {
    $lead = whatsappOpenLead();
    whatsappInbound($lead);
    $sender = app(SendWhatsAppMessage::class);
    $user = User::query()->firstOrFail();

    $first = $sender->send($lead->fresh() ?? $lead, 'One visit.', $user, MessageOrigin::Manual, 'same-key');
    $second = $sender->send($lead->fresh() ?? $lead, 'One visit.', $user, MessageOrigin::Manual, 'same-key');

    expect($second->duplicate)->toBeTrue()
        ->and($second->message->id)->toBe($first->message->id)
        ->and(app(FakeWhatsAppClient::class)->sends)->toBe(1);
});

it('blocks automatic sends after take over and personal attention', function () {
    $lead = whatsappOpenLead();
    whatsappInbound($lead);
    $user = User::query()->firstOrFail();
    $sender = app(SendWhatsAppMessage::class);

    $this->actingAs($user)->post(route('leads.messaging.update', $lead), ['action' => 'take_over'])->assertRedirect();

    expect($lead->fresh()?->taken_over)->toBeTrue();
    expect(fn () => $sender->send($lead->fresh() ?? $lead, 'Auto', $user, MessageOrigin::Automatic, 'takeover-auto'))
        ->toThrow(ValidationException::class);

    $this->actingAs($user)->post(route('leads.messaging.update', $lead), ['action' => 'release'])->assertRedirect();
    $this->actingAs($user)->post(route('leads.messaging.update', $lead), ['action' => 'attention'])->assertRedirect();

    expect($lead->fresh()?->needs_personal_attention)->toBeTrue();
    expect(fn () => $sender->send($lead->fresh() ?? $lead, 'Auto', $user, MessageOrigin::Automatic, 'attention-auto'))
        ->toThrow(ValidationException::class);

    expect(app(FakeWhatsAppClient::class)->sends)->toBe(0);

    $manual = $sender->send($lead->fresh() ?? $lead, 'I have this.', $user, MessageOrigin::Manual, 'attention-manual');

    expect($manual->message->status)->toBe(MessageStatus::Sent);
});

it('forbids a sales user from changing the global pause', function () {
    $user = crmSalesUser();

    $this->actingAs($user)->put(route('whatsapp.settings.update'), [
        'automatic_sends_paused' => true,
        'inbound_product_id' => null,
    ])->assertForbidden();
});

it('reads the cloud api response as accepted and not delivered', function () {
    config([
        'whatsapp.token' => 'test-token',
        'whatsapp.phone_number_id' => '123456',
        'whatsapp.graph_version' => 'v21.0',
        'whatsapp.graph_url' => 'https://graph.facebook.com',
    ]);

    Http::fake([
        'graph.facebook.com/*' => Http::response([
            'messaging_product' => 'whatsapp',
            'messages' => [['id' => 'wamid.cloud.1']],
        ], 200),
    ]);

    $result = (new CloudWhatsAppClient)->sendText('+254722100201', 'Hello', 'cloud-key');

    expect($result->attempt)->toBe(SendAttempt::Accepted)
        ->and($result->providerMessageId)->toBe('wamid.cloud.1');

    Http::assertSent(function ($request): bool {
        return $request->url() === 'https://graph.facebook.com/v21.0/123456/messages'
            && $request->hasHeader('Authorization', 'Bearer test-token')
            && $request['to'] === '254722100201'
            && $request['type'] === 'text';
    });
});

it('treats a cloud api timeout as unknown', function () {
    config([
        'whatsapp.token' => 'test-token',
        'whatsapp.phone_number_id' => '123456',
        'whatsapp.graph_version' => 'v21.0',
        'whatsapp.graph_url' => 'https://graph.facebook.com',
    ]);

    Http::fake(function () {
        throw new ConnectionException('cURL error 28: Operation timed out after 20000 milliseconds');
    });

    expect((new CloudWhatsAppClient)->sendText('+254722100201', 'Hello', 'timeout')->attempt)
        ->toBe(SendAttempt::Unknown);
});

it('treats a cloud api 503 as transient', function () {
    config([
        'whatsapp.token' => 'test-token',
        'whatsapp.phone_number_id' => '123456',
        'whatsapp.graph_version' => 'v21.0',
        'whatsapp.graph_url' => 'https://graph.facebook.com',
    ]);

    Http::fake([
        'graph.facebook.com/*' => Http::response(['error' => 'unavailable'], 503),
    ]);

    expect((new CloudWhatsAppClient)->sendText('+254722100201', 'Hello', 'again')->attempt)
        ->toBe(SendAttempt::Transient);
});
