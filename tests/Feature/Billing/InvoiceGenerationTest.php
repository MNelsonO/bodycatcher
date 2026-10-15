<?php

use App\Ai\AnalysisStatus;
use App\Ai\FakeLlmClient;
use App\Billing\BusinessProfile;
use App\Billing\Invoice;
use App\Billing\InvoiceGeneration;
use App\Contacts\Contact;
use App\Models\User;

function invoiceAiReady(): void
{
    config([
        'ai.api_key' => 'test-key',
        'ai.base_url' => 'https://llm.test/v1',
        'ai.model' => 'desk-model',
    ]);
}

function invoiceAiJson(): string
{
    return json_encode([
        'summary' => 'Monthly laundry retainer for Westlands.',
        'customer_address' => 'Westlands, Nairobi',
        'notes' => 'Thank you for your business.',
        'terms_and_conditions' => 'Payment due within 14 days.',
        'currency' => 'KES',
        'due_date' => '2026-10-30',
        'items' => [[
            'description' => 'Monthly laundry OS',
            'quantity' => 1,
            'unit_price' => 10000,
            'tax_rate' => 16,
        ]],
    ], JSON_THROW_ON_ERROR);
}

it('stores terms on create and shows them on the share page', function () {
    $user = User::factory()->create();
    $contact = Contact::factory()->create();
    BusinessProfile::current();

    $this->actingAs($user)
        ->post('/invoices', [
            'contact_id' => $contact->id,
            'issue_date' => '2026-10-15',
            'due_date' => '2026-10-30',
            'currency' => 'KES',
            'notes' => 'October retainer',
            'terms_and_conditions' => 'Net 14. Late fees apply.',
            'customer_address' => 'Westlands, Nairobi',
            'items' => [[
                'description' => 'Service',
                'quantity' => 1,
                'unit_price' => 1000,
                'tax_rate' => 16,
            ]],
        ])
        ->assertRedirect();

    $invoice = Invoice::query()->firstOrFail();

    expect($invoice->terms_and_conditions)->toBe('Net 14. Late fees apply.')
        ->and($invoice->notes)->toBe('October retainer');

    $this->get('/share/invoices/'.$invoice->share_token)
        ->assertOk()
        ->assertSee('Net 14. Late fees apply.')
        ->assertSee('October retainer');
});

it('generates an invoice draft with AI and keeps history for regenerate', function () {
    invoiceAiReady();
    $user = User::factory()->create();
    $contact = Contact::factory()->create();
    BusinessProfile::current();
    app(FakeLlmClient::class)->push(invoiceAiJson());

    $this->actingAs($user)
        ->post('/invoices/generate', [
            'prompt' => 'Build a monthly laundry retainer invoice for Westlands.',
            'contact_id' => $contact->id,
        ])
        ->assertRedirect();

    $generation = InvoiceGeneration::query()->firstOrFail();

    expect($generation->status)->toBe(AnalysisStatus::Completed)
        ->and($generation->prompt)->toContain('monthly laundry')
        ->and($generation->result['items'][0]['description'])->toBe('Monthly laundry OS')
        ->and($generation->result['terms_and_conditions'])->toBe('Payment due within 14 days.');

    app(FakeLlmClient::class)->push(invoiceAiJson());

    $this->actingAs($user)
        ->post('/invoices/generate', [
            'prompt' => $generation->prompt,
            'from_generation_id' => $generation->id,
            'contact_id' => $contact->id,
        ])
        ->assertRedirect();

    expect(InvoiceGeneration::query()->count())->toBe(2)
        ->and(InvoiceGeneration::query()->latest('id')->value('from_generation_id'))->toBe($generation->id);
});

it('creates an invoice from a completed AI generation', function () {
    invoiceAiReady();
    $user = User::factory()->create();
    $contact = Contact::factory()->create();
    BusinessProfile::current();
    app(FakeLlmClient::class)->push(invoiceAiJson());

    $this->actingAs($user)->post('/invoices/generate', [
        'prompt' => 'Build the retainer invoice.',
        'contact_id' => $contact->id,
    ]);

    $generation = InvoiceGeneration::query()->firstOrFail();

    $this->actingAs($user)
        ->post("/invoices/generations/{$generation->id}/create", [
            'contact_id' => $contact->id,
        ])
        ->assertRedirect();

    $invoice = Invoice::query()->firstOrFail();

    expect($invoice->notes)->toBe('Thank you for your business.')
        ->and($invoice->terms_and_conditions)->toBe('Payment due within 14 days.')
        ->and((float) $invoice->total)->toBe(11600.0)
        ->and($generation->fresh()?->invoice_id)->toBe($invoice->id)
        ->and($generation->fresh()?->applied_at)->not->toBeNull();
});

it('applies a generation onto an existing invoice', function () {
    invoiceAiReady();
    $user = User::factory()->create();
    $contact = Contact::factory()->create();
    BusinessProfile::current();

    $this->actingAs($user)->post('/invoices', [
        'contact_id' => $contact->id,
        'issue_date' => '2026-10-15',
        'due_date' => '2026-10-30',
        'currency' => 'KES',
        'items' => [[
            'description' => 'Placeholder',
            'quantity' => 1,
            'unit_price' => 100,
            'tax_rate' => 0,
        ]],
    ]);

    $invoice = Invoice::query()->firstOrFail();
    app(FakeLlmClient::class)->push(invoiceAiJson());

    $this->actingAs($user)
        ->post("/invoices/{$invoice->id}/generations", [
            'prompt' => 'Replace with the full retainer package.',
        ])
        ->assertRedirect();

    $generation = InvoiceGeneration::query()->where('invoice_id', $invoice->id)->firstOrFail();

    $this->actingAs($user)
        ->post("/invoices/{$invoice->id}/generations/{$generation->id}/apply")
        ->assertRedirect();

    $invoice->refresh();

    expect($invoice->notes)->toBe('Thank you for your business.')
        ->and($invoice->terms_and_conditions)->toBe('Payment due within 14 days.')
        ->and($invoice->items()->count())->toBe(1)
        ->and($invoice->items()->first()?->description)->toBe('Monthly laundry OS');
});
