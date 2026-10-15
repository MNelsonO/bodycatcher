<?php

use App\Contacts\Contact;
use App\Leads\Lead;
use App\Leads\LeadStageHistory;
use App\Models\User;
use App\SalesPipelines\Pipeline;
use App\SalesPipelines\StageKind;

function leadPayload(array $desk, array $overrides = []): array
{
    $contact = $overrides['contact'] ?? Contact::factory()->create();

    return array_merge([
        'contact_id' => $contact->id,
        'product_id' => $desk['product']->id,
        'pipeline_id' => $desk['pipeline']->id,
        'stage_id' => $desk['stage']->id,
        'source' => 'Referral',
        'business_problem' => 'Counter book loses Saturday orders.',
        'current_solution' => 'Exercise book',
        'branch_count' => 1,
        'budget_note' => 'No figure given.',
        'decision_maker_status' => 'yes',
        'purchase_timeline_note' => 'Before December.',
        'next_action' => 'Call the shop.',
        'next_follow_up_on' => null,
    ], $overrides, [
        'contact_id' => $contact->id,
    ]);
}

it('links a lead to a contact, product, and initial stage and writes history', function () {
    $user = crmSalesUser();
    $desk = crmDesk();
    $this->actingAs($user);

    $this->post(route('leads.store'), leadPayload($desk))->assertRedirect();

    $lead = Lead::query()->first();

    expect($lead)->not->toBeNull()
        ->and($lead->product_id)->toBe($desk['product']->id)
        ->and($lead->current_stage_id)->toBe($desk['stage']->id)
        ->and($lead->contact_id)->not->toBeNull();

    $history = LeadStageHistory::query()->where('lead_id', $lead->id)->get();

    expect($history)->toHaveCount(1)
        ->and($history->first()?->from_stage_id)->toBeNull()
        ->and($history->first()?->to_stage_id)->toBe($desk['stage']->id)
        ->and($history->first()?->actor_user_id)->toBe($user->id);
});

it('rejects a second open lead for the same contact and product', function () {
    $desk = crmDesk();
    $this->actingAs(crmSalesUser());
    $contact = Contact::factory()->create();

    $this->post(route('leads.store'), leadPayload($desk, ['contact' => $contact]))->assertRedirect();
    $this->post(route('leads.store'), leadPayload($desk, ['contact' => $contact]))
        ->assertInvalid(['product_id']);

    expect(Lead::query()->count())->toBe(1);
});

it('allows a new lead for the same contact on a different product', function () {
    $desk = crmDesk();
    $this->actingAs(crmSalesUser());
    $contact = Contact::factory()->create();

    $this->post(route('leads.store'), leadPayload($desk, ['contact' => $contact]))->assertRedirect();
    $this->post(route('leads.store'), leadPayload($desk, [
        'contact' => $contact,
        'product_id' => $desk['other']->id,
    ]))->assertRedirect();

    expect(Lead::query()->count())->toBe(2);
});

it('allows a new open lead after the previous one for that product is closed', function () {
    $user = crmSalesUser();
    $desk = crmDesk();
    $this->actingAs($user);
    $contact = Contact::factory()->create();

    $this->post(route('leads.store'), leadPayload($desk, ['contact' => $contact]))->assertRedirect();
    $lead = Lead::query()->firstOrFail();

    $this->post(route('leads.stage', $lead), [
        'stage_id' => $desk['lost']->id,
        'lost_reason' => 'They paused the project.',
    ])->assertRedirect();

    expect($lead->fresh()?->closed_at)->not->toBeNull();

    $this->post(route('leads.store'), leadPayload($desk, ['contact' => $contact]))->assertRedirect();

    expect(Lead::query()->whereNull('closed_at')->count())->toBe(1);
    expect(Lead::query()->count())->toBe(2);
});

it('rejects an illegal stage transition and does not write history', function () {
    $desk = crmDesk();
    $this->actingAs(crmSalesUser());

    $this->post(route('leads.store'), leadPayload($desk))->assertRedirect();
    $lead = Lead::query()->firstOrFail();

    $this->post(route('leads.stage', $lead), [
        'stage_id' => $desk['side']->id,
        'reason' => 'Trying a side door',
    ])->assertInvalid(['stage_id']);

    expect($lead->fresh()?->current_stage_id)->toBe($desk['stage']->id);
    expect(LeadStageHistory::query()->where('lead_id', $lead->id)->count())->toBe(1);
});

it('records a legal transition with the actor and reason', function () {
    $user = crmSalesUser();
    $desk = crmDesk();
    $this->actingAs($user);

    $this->post(route('leads.store'), leadPayload($desk))->assertRedirect();
    $lead = Lead::query()->firstOrFail();

    $this->post(route('leads.stage', $lead), [
        'stage_id' => $desk['won']->id,
        'reason' => 'Signed after the Westlands visit.',
    ])->assertRedirect();

    $lead->refresh();
    $history = LeadStageHistory::query()->where('lead_id', $lead->id)->latest('id')->first();

    expect($lead->current_stage_id)->toBe($desk['won']->id)
        ->and($lead->currentStage->kind)->toBe(StageKind::Won)
        ->and($history?->actor_user_id)->toBe($user->id)
        ->and($history?->reason)->toBe('Signed after the Westlands visit.')
        ->and($history?->from_stage_id)->toBe($desk['stage']->id);
});

it('fails when a lead moves to a lost stage without a reason', function () {
    $desk = crmDesk();
    $this->actingAs(crmSalesUser());

    $this->post(route('leads.store'), leadPayload($desk))->assertRedirect();
    $lead = Lead::query()->firstOrFail();

    $this->post(route('leads.stage', $lead), [
        'stage_id' => $desk['lost']->id,
        'lost_reason' => '   ',
    ])->assertInvalid(['lost_reason']);

    expect($lead->fresh()?->current_stage_id)->toBe($desk['stage']->id);
    expect(LeadStageHistory::query()->where('lead_id', $lead->id)->count())->toBe(1);
});

it('persists a logged message with direction and body', function () {
    $desk = crmDesk();
    $this->actingAs(crmSalesUser());

    $this->post(route('leads.store'), leadPayload($desk))->assertRedirect();
    $lead = Lead::query()->firstOrFail();

    $this->post(route('leads.messages.store', $lead), [
        'direction' => 'inbound',
        'body' => 'Can you pass by the shop on Saturday?',
        'sent_at' => '2026-10-15T09:30',
    ])->assertRedirect();

    $message = $lead->conversation?->messages()->first();

    expect($message)->not->toBeNull()
        ->and($message->direction->value)->toBe('inbound')
        ->and($message->body)->toBe('Can you pass by the shop on Saturday?');
});

it('forbids a sales user from deleting a pipeline', function () {
    $pipeline = Pipeline::factory()->create();
    $sales = User::factory()->create();
    $owner = User::factory()->owner()->create();

    $this->actingAs($sales)
        ->get(route('pipelines.index'))
        ->assertForbidden();

    $this->actingAs($sales)
        ->delete(route('pipelines.destroy', $pipeline))
        ->assertForbidden();

    $this->actingAs($owner)
        ->delete(route('pipelines.destroy', $pipeline))
        ->assertRedirect(route('pipelines.index'));

    expect(Pipeline::query()->find($pipeline->id))->toBeNull();
});

it('sends guests away from the dashboard', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

it('finds a lead by business name or a local Kenyan phone', function () {
    $desk = crmDesk();
    $this->actingAs(crmSalesUser());

    $amina = Contact::factory()->create([
        'full_name' => 'Amina Wanjiku',
        'business_name' => 'FreshFold Laundry',
        'primary_phone' => '+254722100201',
    ]);
    $brian = Contact::factory()->create([
        'full_name' => 'Brian Otieno',
        'business_name' => 'Sparkle and Mop',
        'primary_phone' => '+254733200302',
    ]);

    $this->post(route('leads.store'), leadPayload($desk, ['contact' => $amina]))->assertRedirect();
    $this->post(route('leads.store'), leadPayload($desk, ['contact' => $brian]))->assertRedirect();

    $this->get(route('leads.index', ['q' => 'FreshFold']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('leads.data', 1)
            ->where('leads.data.0.contact_name', 'Amina Wanjiku'));

    $this->get(route('leads.index', ['q' => '0722100201']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('leads.data', 1)
            ->where('leads.data.0.phone', '+254722100201'));
});
