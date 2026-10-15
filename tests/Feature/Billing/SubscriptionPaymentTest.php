<?php

use App\Billing\BillingSubscription;
use App\Billing\Invoice;
use App\Billing\InvoiceStatus;
use App\Billing\PaymentMethod;
use App\Billing\SubscriptionPayment;
use App\Contacts\Contact;
use App\Models\User;

it('seeds default payment methods and lists them in settings', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('payment-methods.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/payment-methods')
            ->has('methods')
            ->where('methods.0.name', 'M-Pesa'));
});

it('lets an owner add a payment method from settings', function () {
    $user = User::factory()->owner()->create();

    $this->actingAs($user)
        ->post(route('payment-methods.store'), [
            'name' => 'Airtel Money',
            'is_active' => true,
        ])
        ->assertRedirect(route('payment-methods.edit'));

    expect(PaymentMethod::query()->where('slug', 'airtel-money')->exists())->toBeTrue();
});

it('records a subscription payment with paid date and method', function () {
    $user = User::factory()->create();
    $contact = Contact::factory()->create();
    $method = PaymentMethod::query()->where('slug', 'mpesa')->firstOrFail();

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
        ->post(route('billing.subscriptions.payments.store', $sub), [
            'paid_on' => '2026-10-12',
            'payment_method_id' => $method->id,
            'reference' => 'QWERTY123',
        ])
        ->assertRedirect(route('billing.subscriptions.index'));

    $payment = SubscriptionPayment::query()->first();
    $sub->refresh();

    expect($payment)->not->toBeNull()
        ->and($payment?->paid_on?->toDateString())->toBe('2026-10-12')
        ->and($payment?->payment_method_id)->toBe($method->id)
        ->and($payment?->reference)->toBe('QWERTY123')
        ->and($sub->last_paid_on?->toDateString())->toBe('2026-10-12')
        ->and($sub->last_payment_method_id)->toBe($method->id)
        ->and($sub->next_due_on?->gt(now()->startOfDay()))->toBeTrue()
        ->and(Invoice::query()->where('status', InvoiceStatus::Paid)->count())->toBe(1);
});

it('shows active subscriptions with payment form on the contact page', function () {
    $user = User::factory()->create();
    $contact = Contact::factory()->create();

    BillingSubscription::query()->create([
        'contact_id' => $contact->id,
        'label' => 'Route desk',
        'amount' => 5000,
        'tax_rate' => 16,
        'cadence' => 'monthly',
        'next_due_on' => now()->addDays(2)->toDateString(),
        'status' => 'active',
        'alert_days_before' => 3,
    ]);

    $this->actingAs($user)
        ->get(route('contacts.show', $contact))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('contacts/show')
            ->has('subscriptions', 1)
            ->has('payment_methods')
            ->where('subscriptions.0.label', 'Route desk'));
});
