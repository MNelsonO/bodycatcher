<?php

use App\Ai\AnthropicClient;
use App\Ai\LlmClient;
use App\Ai\LlmConfiguration;
use App\Calendar\CalendarActivity;
use App\Contacts\Contact;
use App\Conversations\Conversation;
use App\Conversations\Message;
use App\Conversations\MessageStatus;
use App\FollowUps\FollowUpTask;
use App\FollowUps\FollowUpTaskStatus;
use App\FollowUps\Services\FollowUpEngine;
use App\Leads\Lead;
use App\WhatsApp\FakeWhatsAppClient;
use Inertia\Testing\AssertableInertia as Assert;

it('lists chats with the last message and an unread count, and clears it when opened', function () {
    $lead = whatsappOpenLead();
    whatsappInbound($lead, now()->subMinutes(10));
    whatsappInbound($lead, now()->subMinutes(5));
    $conversation = $lead->conversation()->firstOrFail();

    $this->get(route('inbox.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('inbox/index')
            ->has('conversations', 1)
            ->where('conversations.0.contact_name', 'Amina Wanjiku')
            ->where('conversations.0.unread_count', 2)
            ->where('conversations.0.last_message.direction', 'inbound')
            ->where('inboxUnread', 1)
            ->where('thread', null));

    $this->get(route('inbox.show', $conversation))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('thread.id', $conversation->id)
            ->has('thread.messages', 2)
            ->where('thread.contact.full_name', 'Amina Wanjiku')
            ->where('conversations.0.unread_count', 0)
            ->where('inboxUnread', 0));

    expect($conversation->refresh()->last_read_at)->not->toBeNull();
});

it('finds chats by name and by a local Kenyan number', function () {
    $lead = whatsappOpenLead();
    whatsappInbound($lead);

    $this->get(route('inbox.index', ['q' => 'amina']))
        ->assertInertia(fn (Assert $page) => $page->has('conversations', 1));

    $this->get(route('inbox.index', ['q' => '0722100']))
        ->assertInertia(fn (Assert $page) => $page->has('conversations', 1));

    $this->get(route('inbox.index', ['q' => 'nobody']))
        ->assertInertia(fn (Assert $page) => $page->has('conversations', 0));
});

it('sends a WhatsApp reply from the inbox and keeps the operator in the chat', function () {
    $lead = whatsappOpenLead();
    whatsappInbound($lead);
    $conversation = $lead->conversation()->firstOrFail();

    $this->post(route('inbox.messages.store', $conversation), [
        'mode' => 'whatsapp',
        'body' => 'Saturday works. 10am?',
        'idempotency_key' => 'inbox-send-1',
    ])->assertRedirect(route('inbox.show', $conversation));

    $sent = Message::query()->where('idempotency_key', 'inbox-send-1')->firstOrFail();

    expect($sent->status)->toBe(MessageStatus::Sent)
        ->and(app(FakeWhatsAppClient::class)->sends)->toBe(1)
        ->and($conversation->refresh()->last_message_at?->equalTo($sent->sent_at))->toBeTrue();
});

it('logs a message by hand without calling WhatsApp', function () {
    $lead = whatsappOpenLead();
    $conversation = $lead->conversation()->firstOrFail();

    $this->post(route('inbox.messages.store', $conversation), [
        'mode' => 'log_inbound',
        'body' => 'Called and asked for the price list.',
        'sent_at' => '2026-11-03T14:30',
    ])->assertRedirect(route('inbox.show', $conversation));

    $logged = Message::query()->where('conversation_id', $conversation->id)->firstOrFail();

    expect($logged->direction->value)->toBe('inbound')
        ->and($logged->sent_at->toDateTimeString())->toBe('2026-11-03 11:30:00')
        ->and(app(FakeWhatsAppClient::class)->sends)->toBe(0);
});

it('starts a chat with a new number without overwriting an existing contact', function () {
    $lead = whatsappOpenLead();

    $this->post(route('inbox.store'), [
        'full_name' => 'Brian Otieno',
        'phone' => '0711 222 333',
    ])->assertRedirect();

    $brian = Contact::query()->where('primary_phone', '+254711222333')->firstOrFail();
    expect($brian->full_name)->toBe('Brian Otieno')
        ->and(Lead::query()->where('contact_id', $brian->id)->count())->toBe(1)
        ->and(Conversation::query()->where('contact_id', $brian->id)->count())->toBe(1);

    $this->post(route('inbox.store'), [
        'full_name' => 'Someone Else',
        'phone' => '+254722100201',
    ])->assertRedirect(route('inbox.show', $lead->conversation()->firstOrFail()));

    expect($lead->contact->refresh()->full_name)->toBe('Amina Wanjiku');
});

it('schedules a message that sends later as a manual send, even during a takeover', function () {
    $lead = whatsappOpenLead();
    whatsappInbound($lead, now()->subMinutes(30));
    $lead->forceFill(['taken_over' => true])->save();
    $conversation = $lead->conversation()->firstOrFail();
    $sendAt = now('Africa/Nairobi')->addHour()->format('Y-m-d\TH:i');

    $this->post(route('inbox.schedule', $conversation), [
        'body' => 'Reminder: see you at 10am.',
        'send_at' => $sendAt,
    ])->assertRedirect(route('inbox.show', $conversation));

    $task = FollowUpTask::query()->firstOrFail();
    expect($task->status)->toBe(FollowUpTaskStatus::Pending)
        ->and($task->automation_rule_id)->toBeNull();

    app(FollowUpEngine::class)->run();
    expect($task->refresh()->status)->toBe(FollowUpTaskStatus::Pending);

    $this->travel(2)->hours();
    app(FollowUpEngine::class)->run();

    expect($task->refresh()->status)->toBe(FollowUpTaskStatus::Sent)
        ->and($task->message?->origin->value)->toBe('manual')
        ->and(app(FakeWhatsAppClient::class)->sends)->toBe(1);
});

it('refuses to schedule a message in the past', function () {
    $lead = whatsappOpenLead();

    $this->post(route('inbox.schedule', $lead->conversation()->firstOrFail()), [
        'body' => 'Too late',
        'send_at' => now('Africa/Nairobi')->subHour()->format('Y-m-d\TH:i'),
    ])->assertSessionHasErrors('send_at');

    expect(FollowUpTask::query()->count())->toBe(0);
});

it('uses the native Claude client when the provider is anthropic', function () {
    config(['ai.provider' => 'anthropic', 'ai.api_key' => '', 'ai.model' => 'claude-opus-5-5', 'ai.base_url' => '']);
    $configuration = app(LlmConfiguration::class);

    expect($configuration->ready())->toBeFalse();

    config(['ai.api_key' => 'test-key']);

    expect($configuration->ready())->toBeTrue()
        ->and(collect($configuration->variables())->firstWhere('key', 'LLM_BASE_URL')['configured'] ?? null)->toBeTrue();

    app()->detectEnvironment(fn () => 'local');
    expect(app(LlmClient::class))->toBeInstanceOf(AnthropicClient::class);
    app()->detectEnvironment(fn () => 'testing');
});

it('books a timed calendar activity from the inbox and stays in the chat', function () {
    $lead = whatsappOpenLead();
    $conversation = $lead->conversation()->firstOrFail();

    $this->from(route('inbox.show', $conversation))
        ->post(route('calendar.store'), [
            'return_to' => 'back',
            'title' => 'Demo at the shop',
            'activity_on' => '2026-11-20',
            'start_time' => '10:30',
            'duration_minutes' => 45,
            'activity_type' => 'visit',
            'color' => 'blue',
            'location' => 'Westlands',
            'lead_id' => $lead->id,
        ])->assertRedirect(route('inbox.show', $conversation));

    $activity = CalendarActivity::query()->firstOrFail();

    expect($activity->start_time)->toBe('10:30')
        ->and($activity->duration_minutes)->toBe(45)
        ->and($activity->location)->toBe('Westlands');

    $this->get(route('calendar.index', ['month' => '2026-11']))
        ->assertInertia(fn (Assert $page) => $page->where('activities.0.start_time', '10:30'));
});
