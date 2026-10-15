<?php

use App\Billing\BillingSubscription;
use App\Contacts\Contact;
use App\Models\User;

it('lists monthly customers and flags due soon alerts', function () {
    $user = User::factory()->create();
    $contact = Contact::factory()->create();

    BillingSubscription::query()->create([
        'contact_id' => $contact->id,
        'label' => 'Laundry monthly',
        'amount' => 8000,
        'tax_rate' => 16,
        'cadence' => 'monthly',
        'next_due_on' => now()->toDateString(),
        'status' => 'active',
        'alert_days_before' => 3,
    ]);

    $this->actingAs($user)
        ->get('/billing/subscriptions')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('billing/subscriptions/index')
            ->has('subscriptions', 1)
            ->has('alerts.due_subscriptions', 1)
        );
});

it('creates an invoice from a monthly subscription', function () {
    $user = User::factory()->create();
    $contact = Contact::factory()->create();

    $sub = BillingSubscription::query()->create([
        'contact_id' => $contact->id,
        'label' => 'Laundry monthly',
        'amount' => 8000,
        'tax_rate' => 16,
        'cadence' => 'monthly',
        'next_due_on' => now()->toDateString(),
        'status' => 'active',
        'alert_days_before' => 3,
    ]);

    $this->actingAs($user)
        ->post("/billing/subscriptions/{$sub->id}/invoice")
        ->assertRedirect();

    expect(\App\Billing\Invoice::query()->count())->toBe(1)
        ->and($sub->fresh()?->last_invoice_id)->not->toBeNull();
});
