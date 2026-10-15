<?php

use App\Ai\Jobs\RunConditionalAutoReply;
use App\Ai\KnowledgeArticle;
use App\Ai\OperatorEscalation;
use App\Ai\Services\InboundAssistantRouter;
use App\Ai\Services\LiveConversationAssistant;
use App\Conversations\Conversation;
use App\Conversations\Message;
use App\Conversations\MessageOrigin;
use App\Models\User;
use App\Security\Role;
use App\WhatsApp\DeskSetting;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

it('routes to the live assistant when the desk switch is on', function () {
    Mail::fake();
    User::factory()->create(['role' => Role::Owner->value]);
    $lead = whatsappOpenLead();
    whatsappInbound($lead);

    KnowledgeArticle::query()->create([
        'title' => 'Laundry OS pricing lesson',
        'body' => "Q: How much is Laundry OS?\n\nA: Pilot shops get a quoted monthly fee after a short discovery call. Never invent a number in chat — offer to book that call.",
        'category' => 'lesson',
        'product_id' => $lead->product_id,
        'is_active' => true,
        'source' => 'manual',
    ]);

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
    $fake->push("REPLY: Thanks for asking about Laundry OS — we quote after a short discovery call rather than a fixed chat price.\nTOPIC: Laundry OS pricing\nTOPIC_SUMMARY: Customer asked about pricing for Laundry OS.\nMEMORY: Wants clear pricing path.");

    app(InboundAssistantRouter::class)->handle($lead->fresh(), 'How much is Laundry OS?');

    $conversation = Conversation::query()->where('lead_id', $lead->id)->first();
    $auto = Message::query()->where('origin', MessageOrigin::Automatic->value)->first();

    expect($conversation?->topic)->toBe('Laundry OS pricing')
        ->and($auto?->body)->toContain('Laundry OS')
        ->and(app(LiveConversationAssistant::class)->enabled())->toBeTrue();
});

it('escalates instead of answering when knowledge is vague or missing', function () {
    Mail::fake();
    User::factory()->create(['role' => Role::Owner->value]);
    $lead = whatsappOpenLead();
    whatsappInbound($lead);

    KnowledgeArticle::query()->create([
        'title' => 'Customer ask · pricing',
        'body' => "Frequent WhatsApp inbound (seen 3 times):\n\nhow much?\n\nNeeds an operator fact/lesson. Do not invent prices.",
        'category' => 'lesson',
        'product_id' => $lead->product_id,
        'is_active' => true,
        'source' => 'whatsapp_inbox',
    ]);

    $settings = DeskSetting::current();
    $settings->live_assistant_enabled = true;
    $settings->save();

    config([
        'ai.api_key' => 'test-key',
        'ai.base_url' => 'https://api.openai.com/v1',
        'ai.model' => 'gpt-4o-mini',
        'ai.provider' => 'openai',
    ]);

    app(InboundAssistantRouter::class)->handle($lead->fresh(), 'How much is Laundry OS monthly?');

    expect(OperatorEscalation::query()->where('lead_id', $lead->id)->where('status', 'open')->exists())->toBeTrue()
        ->and(Message::query()->where('origin', MessageOrigin::Automatic->value)->count())->toBe(0)
        ->and(app(\App\Ai\FakeLlmClient::class)->calls)->toBe([]);
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
