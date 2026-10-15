<?php

use App\Ai\KnowledgeArticle;
use App\Ai\KnowledgeGap;
use App\Ai\Services\RaiseUnfamiliarQuestion;
use App\Models\User;
use App\Security\Role;

it('raises an unfamiliar inbound question for operator fact feed', function () {
    $lead = whatsappOpenLead();

    KnowledgeArticle::query()->create([
        'title' => 'Laundry OS pricing basics',
        'body' => 'Standard laundry OS pricing starts from a shop pilot.',
        'category' => 'fact',
        'product_id' => $lead->product_id,
        'is_active' => true,
        'source' => 'manual',
    ]);

    $gap = app(RaiseUnfamiliarQuestion::class)->handle(
        $lead,
        'Do you offer on-site physical training sessions for our Nairobi branch managers?',
        'inbound',
    );

    expect($gap)->not->toBeNull()
        ->and($gap?->status)->toBe('open')
        ->and($gap?->contact_name)->toBe($lead->contact->full_name)
        ->and($gap?->product_name)->toBe($lead->product->name)
        ->and($gap?->stage_name)->toBe($lead->currentStage->name)
        ->and($gap?->lead_status)->toBe($lead->outcome());
});

it('does not raise a question the knowledge already covers', function () {
    $lead = whatsappOpenLead();

    KnowledgeArticle::query()->create([
        'title' => 'Training sessions',
        'body' => 'We offer on-site physical training sessions for branch managers in Nairobi.',
        'category' => 'session',
        'product_id' => $lead->product_id,
        'is_active' => true,
        'source' => 'manual',
    ]);

    $gap = app(RaiseUnfamiliarQuestion::class)->handle(
        $lead,
        'Do you offer on-site physical training sessions for branch managers in Nairobi?',
        'inbound',
    );

    expect($gap)->toBeNull();
});

it('saves an operator lesson from a knowledge gap', function () {
    $owner = User::factory()->create(['role' => Role::Owner->value]);
    $lead = whatsappOpenLead();

    $gap = KnowledgeGap::query()->create([
        'lead_id' => $lead->id,
        'contact_id' => $lead->contact_id,
        'product_id' => $lead->product_id,
        'status' => 'open',
        'source' => 'inbound',
        'question' => 'How long is a physical onboarding session?',
        'contact_name' => $lead->contact->full_name,
        'product_name' => $lead->product->name,
        'stage_name' => $lead->currentStage->name,
        'lead_status' => 'open',
    ]);

    $this->actingAs($owner)
        ->post(route('ai.gaps.answer', $gap), [
            'answer' => 'Half-day on-site session with one trainer; book via Nelson.',
            'category' => 'session',
            'title' => 'Physical onboarding session length',
        ])
        ->assertRedirect(route('ai.settings'));

    $gap->refresh();

    expect($gap->status)->toBe('answered')
        ->and($gap->lesson_category)->toBe('session')
        ->and(KnowledgeArticle::query()->where('external_id', 'gap-'.$gap->id)->where('category', 'session')->exists())->toBeTrue()
        ->and($lead->conversation?->fresh()?->memory_summary)->toContain('Physical session taught');
});
