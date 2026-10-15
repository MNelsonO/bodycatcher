<?php

use App\Billing\BusinessProfile;
use App\Billing\Invoice;
use App\Billing\InvoiceStatus;
use App\Billing\TaxLedgerEntry;
use App\Contacts\Contact;
use App\Models\User;

it('creates an invoice with tax and share token', function () {
    $user = User::factory()->create();
    $contact = Contact::factory()->create();

    $this->actingAs($user)
        ->post('/invoices', [
            'contact_id' => $contact->id,
            'issue_date' => '2026-10-15',
            'due_date' => '2026-10-30',
            'currency' => 'KES',
            'notes' => 'October laundry retainer',
            'customer_address' => 'Westlands, Nairobi',
            'items' => [
                [
                    'description' => 'Monthly laundry OS',
                    'quantity' => 1,
                    'unit_price' => 10000,
                    'tax_rate' => 16,
                ],
            ],
        ])
        ->assertRedirect();

    $invoice = Invoice::query()->first();

    expect($invoice)->not->toBeNull()
        ->and($invoice?->status)->toBe(InvoiceStatus::Draft)
        ->and((float) $invoice?->subtotal)->toBe(10000.0)
        ->and((float) $invoice?->tax_total)->toBe(1600.0)
        ->and((float) $invoice?->total)->toBe(11600.0)
        ->and($invoice?->share_token)->not->toBeEmpty();
});

it('marks an invoice paid and writes a tax ledger row', function () {
    $user = User::factory()->create();
    $contact = Contact::factory()->create();
    BusinessProfile::current();

    $this->actingAs($user)->post('/invoices', [
        'contact_id' => $contact->id,
        'issue_date' => '2026-10-15',
        'due_date' => '2026-10-30',
        'currency' => 'KES',
        'items' => [[
            'description' => 'Service',
            'quantity' => 1,
            'unit_price' => 1000,
            'tax_rate' => 16,
        ]],
    ]);

    $invoice = Invoice::query()->firstOrFail();

    $this->actingAs($user)
        ->post("/invoices/{$invoice->id}/mark-paid")
        ->assertRedirect();

    expect($invoice->fresh()?->status)->toBe(InvoiceStatus::Paid)
        ->and(TaxLedgerEntry::query()->where('source', 'invoice')->where('source_id', $invoice->id)->exists())->toBeTrue();
});

it('serves a public share page for an invoice token', function () {
    $user = User::factory()->create();
    $contact = Contact::factory()->create();
    BusinessProfile::current();

    $this->actingAs($user)->post('/invoices', [
        'contact_id' => $contact->id,
        'issue_date' => '2026-10-15',
        'due_date' => '2026-10-30',
        'currency' => 'KES',
        'items' => [[
            'description' => 'Service',
            'quantity' => 1,
            'unit_price' => 500,
            'tax_rate' => 0,
        ]],
    ]);

    $invoice = Invoice::query()->firstOrFail();

    $this->get('/share/invoices/'.$invoice->share_token)
        ->assertOk()
        ->assertSee($invoice->number);
});
