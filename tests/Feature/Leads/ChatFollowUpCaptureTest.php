<?php

use App\Contacts\ConsentStatus;
use App\Contacts\Contact;
use App\Leads\Jobs\CaptureChatFollowUpJob;
use App\Leads\Lead;
use App\Leads\Services\CaptureChatFollowUp;
use App\Models\User;
use App\Security\Role;
use Illuminate\Support\Carbon;

beforeEach(function () {
    config(['app.display_timezone' => 'Africa/Nairobi']);
    Carbon::setTestNow(Carbon::parse('2026-10-16 10:00:00', 'Africa/Nairobi'));
});

afterEach(function () {
    Carbon::setTestNow();
});

it('captures a chat date onto the lead and ranks it for follow-up', function () {
    $owner = User::factory()->create(['role' => Role::Owner->value]);
    $desk = crmDesk();

    $contact = Contact::factory()->create([
        'full_name' => 'Amina Wanjiku',
        'consent_status' => ConsentStatus::OptedIn,
    ]);

    $lead = Lead::factory()->create([
        'contact_id' => $contact->id,
        'product_id' => $desk['product']->id,
        'pipeline_id' => $desk['pipeline']->id,
        'current_stage_id' => $desk['stage']->id,
        'next_follow_up_on' => null,
    ]);

    $captured = app(CaptureChatFollowUp::class)->handle(
        $lead,
        'Hi, call me tomorrow at 2pm about the laundry OS',
    );

    $lead->refresh();

    expect($captured)->toBeTrue()
        ->and($lead->next_follow_up_on?->toDateString())->toBe('2026-10-17')
        ->and($lead->follow_up_source)->toBe('chat')
        ->and($lead->next_action)->toContain('Chat:')
        ->and($lead->follow_up_rank)->toBeGreaterThan(0)
        ->and($lead->next_conversation_at)->not->toBeNull()
        ->and($owner->email)->not->toBe('');
});

it('skips opted-out contacts', function () {
    $desk = crmDesk();
    User::factory()->create(['role' => Role::Owner->value]);

    $contact = Contact::factory()->create([
        'consent_status' => ConsentStatus::OptedOut,
    ]);

    $lead = Lead::factory()->create([
        'contact_id' => $contact->id,
        'product_id' => $desk['product']->id,
        'pipeline_id' => $desk['pipeline']->id,
        'current_stage_id' => $desk['stage']->id,
    ]);

    expect(app(CaptureChatFollowUp::class)->handle($lead, 'Call me tomorrow'))->toBeFalse();
});

it('queues capture from the job', function () {
    $desk = crmDesk();
    User::factory()->create(['role' => Role::Owner->value]);

    $lead = Lead::factory()->create([
        'contact_id' => Contact::factory()->create([
            'consent_status' => ConsentStatus::OptedIn,
        ])->id,
        'product_id' => $desk['product']->id,
        'pipeline_id' => $desk['pipeline']->id,
        'current_stage_id' => $desk['stage']->id,
    ]);

    (new CaptureChatFollowUpJob($lead->id, 'Remind me next Monday to follow up'))->handle(
        app(CaptureChatFollowUp::class),
    );

    expect($lead->refresh()->follow_up_source)->toBe('chat')
        ->and($lead->next_follow_up_on)->not->toBeNull();
});
