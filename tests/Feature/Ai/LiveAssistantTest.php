<?php

use App\Ai\Jobs\RunConditionalAutoReply;
use App\Ai\Services\InboundAssistantRouter;
use App\Ai\Services\LiveConversationAssistant;
use App\Conversations\Conversation;
use App\Conversations\Message;
use App\Conversations\MessageOrigin;
use App\Models\User;
use App\Security\Role;
use App\WhatsApp\DeskSetting;
use Illuminate\Support\Facades\Queue;

it('routes to the live assistant when the desk switch is on', function () {
    User::factory()->create(['role' => Role::Owner->value]);
    $lead = whatsappOpenLead();
    whatsappInbound($lead);

    $settings = DeskSetting::current();
    $settings->live_assistant_enabled = true;
    $settings->save();

    config([
        'ai.api_key' => 'test-key',
        'ai.base_url' => 'https://api.openai.com/v1',
        'ai.model' => 'gpt-4o-mini',
        'ai.provider' => 'openai',
    ]);

    $fake = app(\App\Ai\FakeLlmClient::class);
    $fake->push("REPLY: Thanks for asking about Laundry OS.\nTOPIC: Laundry OS pricing\nTOPIC_SUMMARY: Customer asked about pricing for Laundry OS.");

    app(InboundAssistantRouter::class)->handle($lead->fresh(), 'How much is Laundry OS?');

    $conversation = Conversation::query()->where('lead_id', $lead->id)->first();
    $auto = Message::query()->where('origin', MessageOrigin::Automatic->value)->first();

    expect($conversation?->topic)->toBe('Laundry OS pricing')
        ->and($auto?->body)->toContain('Laundry OS')
        ->and(app(LiveConversationAssistant::class)->enabled())->toBeTrue();
});

it('keeps rule-based replies when the live assistant is off', function () {
    Queue::fake([RunConditionalAutoReply::class]);
    whatsappConfig();
    crmDesk();

    expect(app(LiveConversationAssistant::class)->enabled())->toBeFalse();

    postWhatsAppWebhook(whatsappTextPayload('254722100201', 'Need laundry pickup', 'wamid.live.1'))
        ->assertOk();

    Queue::assertPushed(RunConditionalAutoReply::class);
});
