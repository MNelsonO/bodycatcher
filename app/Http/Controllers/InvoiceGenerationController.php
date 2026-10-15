<?php

namespace App\Http\Controllers;

use App\Billing\Invoice;
use App\Billing\InvoiceGeneration;
use App\Billing\Services\GenerateInvoiceDraft;
use App\Billing\Services\InvoiceService;
use App\Http\Requests\InvoiceGenerationRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class InvoiceGenerationController extends Controller
{
    public function storeDraft(InvoiceGenerationRequest $request, GenerateInvoiceDraft $generator): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $generation = $generator->request([
            'prompt' => (string) ($request->input('prompt') ?? ''),
            'contact_id' => $request->filled('contact_id') ? (int) $request->input('contact_id') : null,
            'from_generation_id' => $request->filled('from_generation_id')
                ? (int) $request->input('from_generation_id')
                : null,
        ], $actor);

        Inertia::flash('toast', [
            'type' => $generation->status->value === 'unavailable' ? 'error' : 'success',
            'message' => match ($generation->status->value) {
                'unavailable' => 'AI is not configured. Set LLM keys under Intelligence.',
                'queued', 'completed' => 'Invoice draft generation started.',
                default => 'Invoice draft generation saved.',
            },
        ]);

        return redirect()->route('invoices.create', ['generation' => $generation->id]);
    }

    public function store(InvoiceGenerationRequest $request, Invoice $invoice, GenerateInvoiceDraft $generator): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $generation = $generator->request([
            'prompt' => (string) ($request->input('prompt') ?? ''),
            'contact_id' => $invoice->contact_id,
            'invoice_id' => $invoice->id,
            'from_generation_id' => $request->filled('from_generation_id')
                ? (int) $request->input('from_generation_id')
                : null,
        ], $actor);

        Inertia::flash('toast', [
            'type' => $generation->status->value === 'unavailable' ? 'error' : 'success',
            'message' => match ($generation->status->value) {
                'unavailable' => 'AI is not configured. Set LLM keys under Intelligence.',
                default => 'Invoice AI generation queued.',
            },
        ]);

        return redirect()->route('invoices.show', $invoice);
    }

    public function apply(
        Invoice $invoice,
        InvoiceGeneration $generation,
        GenerateInvoiceDraft $generator,
        InvoiceService $invoices,
    ): RedirectResponse {
        abort_unless(
            $generation->invoice_id === null || $generation->invoice_id === $invoice->id,
            404,
        );

        $generator->apply($generation, $invoice, $invoices);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'AI draft applied to invoice.']);

        return redirect()->route('invoices.show', $invoice);
    }

    public function linkAndApply(
        InvoiceGeneration $generation,
        InvoiceService $invoices,
        Request $request,
    ): RedirectResponse {
        /** @var User $actor */
        $actor = $request->user();
        abort_unless($actor !== null, 403);

        $request->validate([
            'contact_id' => ['nullable', 'integer', 'exists:contacts,id'],
        ]);

        if ($generation->invoice_id !== null) {
            return redirect()->route('invoices.show', $generation->invoice_id);
        }

        /** @var array<string, mixed> $result */
        $result = $generation->result ?? [];
        $items = is_array($result['items'] ?? null) ? $result['items'] : [];

        if ($items === []) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'This generation has no line items yet.']);

            return redirect()->route('invoices.create', ['generation' => $generation->id]);
        }

        $contactId = $request->filled('contact_id')
            ? (int) $request->input('contact_id')
            : ($generation->contact_id ?? null);

        if ($contactId === null) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Pick a customer before applying the AI draft.']);

            return redirect()->route('invoices.create', ['generation' => $generation->id]);
        }

        $invoice = $invoices->create([
            'contact_id' => $contactId,
            'issue_date' => now()->toDateString(),
            'due_date' => is_string($result['due_date'] ?? null)
                ? $result['due_date']
                : now()->addDays(14)->toDateString(),
            'currency' => is_string($result['currency'] ?? null) ? $result['currency'] : 'KES',
            'notes' => is_string($result['notes'] ?? null) ? $result['notes'] : null,
            'terms_and_conditions' => is_string($result['terms_and_conditions'] ?? null)
                ? $result['terms_and_conditions']
                : null,
            'customer_address' => is_string($result['customer_address'] ?? null)
                ? $result['customer_address']
                : null,
        ], array_map(static fn (array $item): array => [
            'description' => (string) $item['description'],
            'quantity' => (float) $item['quantity'],
            'unit_price' => (float) $item['unit_price'],
            'tax_rate' => (float) ($item['tax_rate'] ?? 0),
        ], array_values(array_filter($items, 'is_array'))), $actor);

        $generation->invoice_id = $invoice->id;
        $generation->contact_id = $contactId;
        $generation->applied_at = now();
        $generation->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Invoice '.$invoice->number.' created from AI draft.']);

        return redirect()->route('invoices.show', $invoice);
    }
}
