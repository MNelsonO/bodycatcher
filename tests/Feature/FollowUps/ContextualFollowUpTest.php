<?php

use App\Ai\FakeLlmClient;
use App\Ai\KnowledgeArticle;
use App\Conversations\Conversation;
use App\Conversations\Message;
use App\Conversations\MessageOrigin;
use App\FollowUps\FollowUpAction;
use App\FollowUps\FollowUpTask;
use App\FollowUps\FollowUpTaskStatus;
use App\WhatsApp\FakeWhatsAppClient;

it('sends a memory-aware follow-up from prior chats and knowledge', function () {
    config([
        'ai.api_key' => 'test-key',
        'ai.base_url' => 'https://api.openai.com/v1',
        'ai.model' => 'gpt-4o-mini',
        'ai.provider' => 'openai',
    ]);

    $lead = followUpReadyLead();
    $conversation = Conversation::query()->where('lead_id', $lead->id)->firstOrFail();
    $conversation->update([
        'topic' => 'Laundry OS demo',
        'topic_summary' => 'Asked for a Saturday visit.',
        'memory_summary' => 'Prefers short WhatsApp notes; cares about Saturday timing.',
    ]);

    KnowledgeArticle::query()->create([
        'title' => 'Laundry OS demo tips',
        'body' => 'Q: When can we demo?\n\nA: Offer a Saturday morning demo for laundry shops in Nairobi and confirm who will join.',
        'category' => 'lesson',
        'product_id' => $lead->product_id,
        'is_active' => true,
        'source' => 'manual',
    ]);

    $rule = followUpRule([
        'body' => 'Hi {contact}, checking on {business} about {action}.',
        'use_conversation_memory' => true,
        'action' => FollowUpAction::SendWhatsApp->value,
    ]);

    $fake = app(FakeLlmClient::class);
    $fake->push("REPLY: Amina, still good for that Saturday Laundry OS visit in Westlands?\nMEMORY: Likes Saturday demos; keep messages short.");

    $this->artisan('follow-ups:run')->assertSuccessful();

    $task = FollowUpTask::query()->firstOrFail();
    $client = app(FakeWhatsAppClient::class);
    $sent = Message::query()->where('origin', MessageOrigin::Automatic)->firstOrFail();

    expect($task->status)->toBe(FollowUpTaskStatus::Sent)
        ->and($client->calls[0]['body'])->toContain('Saturday')
        ->and($client->calls[0]['body'])->not->toBe('Hi Amina Wanjiku, checking on Westlands Wash about book a visit.')
        ->and($sent->body)->toContain('Saturday')
        ->and($conversation->fresh()?->memory_summary)->toContain('Saturday')
        ->and($fake->calls[0]['user'])->toContain('Conversation history')
        ->and($fake->calls[0]['user'])->toContain('Laundry OS demo tips')
        ->and($rule->use_conversation_memory)->toBeTrue();
});

it('falls back to the template when chat memory is off', function () {
    config([
        'ai.api_key' => 'test-key',
        'ai.base_url' => 'https://api.openai.com/v1',
        'ai.model' => 'gpt-4o-mini',
    ]);

    $lead = followUpReadyLead();
    followUpRule([
        'body' => 'Hi {contact}, checking on {business} about {action}.',
        'use_conversation_memory' => false,
    ]);

    $fake = app(FakeLlmClient::class);

    $this->artisan('follow-ups:run')->assertSuccessful();

    expect(app(FakeWhatsAppClient::class)->calls[0]['body'])
        ->toBe('Hi Amina Wanjiku, checking on Westlands Wash about book a visit.')
        ->and($fake->calls)->toBe([]);
});
