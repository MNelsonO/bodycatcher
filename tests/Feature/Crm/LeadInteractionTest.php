<?php

use App\FollowUps\FollowUpAction;
use App\FollowUps\FollowUpTask;
use App\FollowUps\FollowUpTaskStatus;
use App\Leads\InteractionType;
use App\Leads\Lead;
use App\Leads\LeadInteraction;

it('records a phone call with a backdated occurred_at', function () {
    $user = crmSalesUser();
    $desk = crmDesk();
    $this->actingAs($user);

    $lead = Lead::factory()->create([
        'contact_id' => \App\Contacts\Contact::factory()->create()->id,
        'product_id' => $desk['product']->id,
        'pipeline_id' => $desk['pipeline']->id,
        'current_stage_id' => $desk['stage']->id,
    ]);

    $this->post(route('leads.interactions.store', $lead), [
        'type' => 'phone_call',
        'direction' => 'outbound',
        'body' => 'Called about the Westlands demo. Left a voicemail.',
        'occurred_at' => '2026-10-10T14:30',
        'duration_minutes' => 8,
        'outcome' => 'Voicemail',
    ])->assertRedirect(route('leads.show', $lead));

    $row = LeadInteraction::query()->first();

    expect($row)->not->toBeNull()
        ->and($row?->type)->toBe(InteractionType::PhoneCall)
        ->and($row?->body)->toContain('Westlands demo')
        ->and($row?->duration_minutes)->toBe(8)
        ->and($row?->outcome)->toBe('Voicemail')
        ->and($row?->occurred_at?->timezone('Africa/Nairobi')->format('Y-m-d H:i'))->toBe('2026-10-10 14:30');
});

it('stops future conversations and cancels pending follow-up tasks', function () {
    $user = crmSalesUser();
    $desk = crmDesk();
    $this->actingAs($user);

    $lead = Lead::factory()->create([
        'contact_id' => \App\Contacts\Contact::factory()->create()->id,
        'product_id' => $desk['product']->id,
        'pipeline_id' => $desk['pipeline']->id,
        'current_stage_id' => $desk['stage']->id,
        'conversations_stopped' => false,
        'automation_paused' => false,
    ]);

    $task = FollowUpTask::query()->create([
        'lead_id' => $lead->id,
        'automation_rule_id' => null,
        'idempotency_key' => 'stop-test-'.$lead->id,
        'action' => FollowUpAction::SendWhatsApp,
        'body' => 'Checking in',
        'status' => FollowUpTaskStatus::Pending,
        'due_at' => now()->addDay(),
        'cancel_on_reply' => true,
    ]);

    $this->post(route('leads.messaging.update', $lead), [
        'action' => 'stop_conversations',
    ])->assertRedirect(route('leads.show', $lead));

    expect($lead->fresh()?->conversations_stopped)->toBeTrue()
        ->and($lead->fresh()?->automation_paused)->toBeTrue()
        ->and($task->fresh()?->status)->toBe(FollowUpTaskStatus::Cancelled);
});

it('schedules the next conversation and clears a hard stop', function () {
    $user = crmSalesUser();
    $desk = crmDesk();
    $this->actingAs($user);

    $lead = Lead::factory()->create([
        'contact_id' => \App\Contacts\Contact::factory()->create()->id,
        'product_id' => $desk['product']->id,
        'pipeline_id' => $desk['pipeline']->id,
        'current_stage_id' => $desk['stage']->id,
        'conversations_stopped' => true,
        'automation_paused' => true,
    ]);

    $this->put(route('leads.conversation-schedule.update', $lead), [
        'next_conversation_at' => '2026-10-20T09:00',
        'next_action' => 'Demo walkthrough',
    ])->assertRedirect(route('leads.show', $lead));

    $lead->refresh();

    expect($lead->conversations_stopped)->toBeFalse()
        ->and($lead->automation_paused)->toBeFalse()
        ->and($lead->next_action)->toBe('Demo walkthrough')
        ->and($lead->next_conversation_at?->timezone('Africa/Nairobi')->format('Y-m-d H:i'))->toBe('2026-10-20 09:00');
});

it('exposes stage buckets and interaction metrics on the command centre', function () {
    $user = crmSalesUser();
    $this->actingAs($user);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->has('metrics.stage_buckets.follow_up.total')
            ->has('metrics.stage_buckets.almost_buying.total')
            ->has('metrics.stage_buckets.meetings.total')
            ->has('metrics.interactions.today')
            ->has('metrics.scheduled_conversations')
            ->has('metrics.stopped_conversations'));
});

it('shows google calendar activation status on the calendar page', function () {
    $user = crmSalesUser();
    $this->actingAs($user);

    $this->get(route('calendar.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('calendar/index')
            ->has('google_calendar.activated')
            ->has('google_calendar.configured')
            ->where('google_calendar.activated', false));
});
