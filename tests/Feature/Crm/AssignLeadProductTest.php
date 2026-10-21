<?php

use App\Contacts\Contact;
use App\Leads\ActivityEvent;
use App\Leads\ActivityLog;
use App\Leads\Lead;

it('assigns a different product on an open lead', function () {
    $user = crmSalesUser();
    $desk = crmDesk();
    $this->actingAs($user);
    $contact = Contact::factory()->create();

    $this->post(route('leads.store'), leadPayload($desk, ['contact' => $contact]))
        ->assertRedirect();

    $lead = Lead::query()->firstOrFail();

    $this->put(route('leads.update', $lead), [
        'product_id' => $desk['other']->id,
    ])->assertRedirect(route('leads.show', $lead));

    expect($lead->fresh()?->product_id)->toBe($desk['other']->id);

    $activity = ActivityLog::query()
        ->where('lead_id', $lead->id)
        ->where('event', ActivityEvent::ProductChanged)
        ->first();

    expect($activity)->not->toBeNull();
});

it('rejects assigning a product that already has an open lead for the contact', function () {
    $desk = crmDesk();
    $this->actingAs(crmSalesUser());
    $contact = Contact::factory()->create();

    $this->post(route('leads.store'), leadPayload($desk, ['contact' => $contact]))
        ->assertRedirect();
    $this->post(route('leads.store'), leadPayload($desk, [
        'contact' => $contact,
        'product_id' => $desk['other']->id,
    ]))->assertRedirect();

    $lead = Lead::query()->where('product_id', $desk['product']->id)->firstOrFail();

    $this->put(route('leads.update', $lead), [
        'product_id' => $desk['other']->id,
    ])->assertInvalid(['product_id']);
});

it('shows new-contact and intent fields on the lead page', function () {
    $desk = crmDesk();
    $this->actingAs(crmSalesUser());

    $this->post(route('leads.store'), leadPayload($desk))->assertRedirect();
    $lead = Lead::query()->firstOrFail();

    $this->get(route('leads.show', $lead))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('leads/show')
            ->where('lead.contact_is_new', true)
            ->where('lead.conversation_is_new', true)
            ->has('lead.intent')
            ->has('lead.pipeline_stages')
            ->has('product_options')
        );
});
