<?php

use App\Ai\AutoReplyRule;
use App\Ai\Jobs\RunConditionalAutoReply;
use App\Ai\Services\ConditionalAutoReply;
use App\Conversations\Message;
use App\Conversations\MessageOrigin;
use App\Models\User;
use App\Security\Role;
use Illuminate\Support\Facades\Queue;

it('does not auto-reply when no rule matches', function () {
    $lead = whatsappOpenLead();
    whatsappInbound($lead);

    AutoReplyRule::query()->create([
        'name' => 'Price only',
        'is_active' => true,
        'priority' => 10,
        'match_type' => 'contains',
        'match_value' => 'price',
        'pipeline_stage_id' => null,
        'reply_mode' => 'template',
        'template_body' => 'Thanks {contact}, we will share pricing.',
        'require_open_window' => true,
    ]);

    app(ConditionalAutoReply::class)->handle($lead, 'Hello there');

    expect(Message::query()->where('origin', MessageOrigin::Automatic->value)->count())->toBe(0);
});

it('sends a template auto-reply when a rule matches', function () {
    User::factory()->create([
        'role' => Role::Owner->value,
        'email' => 'owner-auto@kemet.test',
    ]);
    $lead = whatsappOpenLead();
    whatsappInbound($lead);

    AutoReplyRule::query()->create([
        'name' => 'Greeting',
        'is_active' => true,
        'priority' => 10,
        'match_type' => 'contains',
        'match_value' => 'hello',
        'pipeline_stage_id' => null,
        'reply_mode' => 'template',
        'template_body' => 'Hi {contact}, Kemet received your note.',
        'require_open_window' => true,
    ]);

    app(ConditionalAutoReply::class)->handle($lead->fresh(), 'hello from Nairobi');

    $auto = Message::query()->where('origin', MessageOrigin::Automatic->value)->first();

    expect($auto)->not->toBeNull()
        ->and($auto?->body)->toContain('Kemet received your note');
});

it('queues conditional auto-reply after inbound processing', function () {
    Queue::fake([RunConditionalAutoReply::class]);
    whatsappConfig();
    crmDesk();

    postWhatsAppWebhook(whatsappTextPayload('254722100201', 'Need laundry pickup', 'wamid.auto.1'))
        ->assertOk();

    Queue::assertPushed(RunConditionalAutoReply::class);
});
