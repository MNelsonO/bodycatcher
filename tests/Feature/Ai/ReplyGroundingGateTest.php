<?php

use App\Ai\KnowledgeArticle;
use App\Ai\Services\ReplyGroundingGate;

it('rejects vague placeholder knowledge as grounding', function () {
    $lead = whatsappOpenLead();
    whatsappInbound($lead);

    KnowledgeArticle::query()->create([
        'title' => 'Customer ask · demo',
        'body' => "Frequent WhatsApp inbound:\n\ndemo?\n\nNeeds an operator fact/lesson. Do not invent prices.",
        'category' => 'lesson',
        'product_id' => $lead->product_id,
        'is_active' => true,
        'source' => 'whatsapp_inbox',
    ]);

    $result = app(ReplyGroundingGate::class)->evaluate($lead->fresh(), 'Can we book a demo this Saturday?');

    expect($result['ok'])->toBeFalse()
        ->and($result['lessons'])->toHaveCount(0);
});

it('accepts solid lessons with conversation history', function () {
    $lead = whatsappOpenLead();
    whatsappInbound($lead);
    $lead->conversation?->update([
        'memory_summary' => 'Prefers Saturday demos and short WhatsApp notes.',
    ]);

    KnowledgeArticle::query()->create([
        'title' => 'Demo booking lesson',
        'body' => "Q: Can we book a demo this Saturday?\n\nA: Yes — offer Saturday morning and confirm who from their shop will join.",
        'category' => 'lesson',
        'product_id' => $lead->product_id,
        'is_active' => true,
        'source' => 'operator_fact_feed',
    ]);

    $result = app(ReplyGroundingGate::class)->evaluate($lead->fresh(['conversation.messages', 'product', 'contact', 'currentStage']), 'Can we book a demo this Saturday?');

    expect($result['ok'])->toBeTrue()
        ->and($result['lessons']->count())->toBeGreaterThan(0);
});
