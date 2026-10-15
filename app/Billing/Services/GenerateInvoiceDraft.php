<?php

namespace App\Billing\Services;

use App\Ai\AnalysisStatus;
use App\Ai\LlmClient;
use App\Ai\LlmConfiguration;
use App\Billing\BusinessProfile;
use App\Billing\Invoice;
use App\Billing\InvoiceGeneration;
use App\Billing\Jobs\RunInvoiceGeneration;
use App\Contacts\Contact;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

class GenerateInvoiceDraft
{
    public function __construct(
        private LlmConfiguration $configuration,
        private LlmClient $client,
    ) {}

    /**
     * @param  array{prompt: string, contact_id?: int|null, invoice_id?: int|null, from_generation_id?: int|null}  $input
     */
    public function request(array $input, User $actor): InvoiceGeneration
    {
        $invoiceId = isset($input['invoice_id']) ? (int) $input['invoice_id'] : null;
        $fromId = isset($input['from_generation_id']) ? (int) $input['from_generation_id'] : null;

        if ($invoiceId !== null) {
            $queued = InvoiceGeneration::query()
                ->where('invoice_id', $invoiceId)
                ->where('status', AnalysisStatus::Queued)
                ->first();

            if ($queued !== null) {
                return $queued;
            }
        }

        $prompt = trim((string) $input['prompt']);

        if ($fromId !== null && $prompt === '') {
            $parent = InvoiceGeneration::query()->find($fromId);
            $prompt = is_string($parent?->prompt) ? $parent->prompt : '';
        }

        if (! $this->configuration->ready()) {
            return InvoiceGeneration::query()->create([
                'invoice_id' => $invoiceId,
                'contact_id' => $input['contact_id'] ?? null,
                'requested_by_user_id' => $actor->id,
                'from_generation_id' => $fromId,
                'prompt' => $prompt,
                'status' => AnalysisStatus::Unavailable,
                'error' => 'The language model is not configured.',
            ]);
        }

        $generation = InvoiceGeneration::query()->create([
            'invoice_id' => $invoiceId,
            'contact_id' => $input['contact_id'] ?? null,
            'requested_by_user_id' => $actor->id,
            'from_generation_id' => $fromId,
            'prompt' => $prompt,
            'status' => AnalysisStatus::Queued,
        ]);

        try {
            RunInvoiceGeneration::dispatch($generation->id);
        } catch (Throwable) {
            // Row stays queued for `php artisan ai:retry`.
        }

        return $generation->refresh();
    }

    public function run(int $generationId): void
    {
        $generation = InvoiceGeneration::query()->find($generationId);

        if ($generation === null || $generation->status !== AnalysisStatus::Queued) {
            return;
        }

        try {
            $this->complete($generation);
        } catch (Throwable $exception) {
            $generation->fill([
                'status' => AnalysisStatus::Failed,
                'error' => $exception->getMessage(),
                'completed_at' => now(),
            ]);
            $generation->save();
        }
    }

    /**
     * Apply a completed generation onto an invoice (notes, terms, address, line items).
     */
    public function apply(InvoiceGeneration $generation, Invoice $invoice, InvoiceService $invoices): Invoice
    {
        if ($generation->status !== AnalysisStatus::Completed) {
            return $invoice;
        }

        /** @var array<string, mixed> $result */
        $result = $generation->result ?? [];
        $items = $result['items'] ?? [];

        if (! is_array($items) || $items === []) {
            return $invoice;
        }

        $normalized = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $description = trim((string) ($item['description'] ?? ''));

            if ($description === '') {
                continue;
            }

            $normalized[] = [
                'description' => $description,
                'quantity' => (float) ($item['quantity'] ?? 1),
                'unit_price' => (float) ($item['unit_price'] ?? 0),
                'tax_rate' => (float) ($item['tax_rate'] ?? 0),
            ];
        }

        if ($normalized === []) {
            return $invoice;
        }

        $contactId = $invoice->contact_id;

        if (isset($result['contact_id']) && is_numeric($result['contact_id'])) {
            $contactId = (int) $result['contact_id'];
        } elseif ($generation->contact_id !== null) {
            $contactId = $generation->contact_id;
        }

        $updated = $invoices->update($invoice, [
            'contact_id' => $contactId,
            'issue_date' => $invoice->issue_date?->toDateString() ?? now()->toDateString(),
            'due_date' => isset($result['due_date']) && is_string($result['due_date']) && $result['due_date'] !== ''
                ? $result['due_date']
                : ($invoice->due_date?->toDateString() ?? now()->addDays(14)->toDateString()),
            'currency' => is_string($result['currency'] ?? null) && strlen((string) $result['currency']) === 3
                ? strtoupper((string) $result['currency'])
                : $invoice->currency,
            'notes' => is_string($result['notes'] ?? null) ? $result['notes'] : $invoice->notes,
            'terms_and_conditions' => is_string($result['terms_and_conditions'] ?? null)
                ? $result['terms_and_conditions']
                : $invoice->terms_and_conditions,
            'customer_address' => is_string($result['customer_address'] ?? null)
                ? $result['customer_address']
                : $invoice->customer_address,
        ], $normalized);

        $generation->applied_at = now();
        $generation->invoice_id = $updated->id;
        $generation->save();

        return $updated;
    }

    private function complete(InvoiceGeneration $generation): void
    {
        $profile = BusinessProfile::current();
        $invoice = $generation->invoice_id !== null
            ? Invoice::query()->with(['contact', 'items'])->find($generation->invoice_id)
            : null;
        $contact = $generation->contact_id !== null
            ? Contact::query()->find($generation->contact_id)
            : $invoice?->contact;

        $raw = $this->client->complete(
            $this->systemPrompt(),
            $this->userPrompt($generation, $profile, $invoice, $contact),
        );

        $parsed = $this->parse($raw, $profile);

        DB::transaction(function () use ($generation, $parsed, $raw): void {
            $locked = InvoiceGeneration::query()->lockForUpdate()->find($generation->id);

            if ($locked === null || $locked->status !== AnalysisStatus::Queued) {
                return;
            }

            $locked->fill([
                'status' => AnalysisStatus::Completed,
                'result' => $parsed,
                'raw_response' => $raw,
                'error' => null,
                'completed_at' => now(),
            ]);
            $locked->save();
        });
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
You draft invoices for a B2B sales desk. Reply with JSON only, no markdown.
Shape:
{
  "summary": "short operator note",
  "customer_address": "optional bill-to address line",
  "notes": "optional invoice notes",
  "terms_and_conditions": "optional payment / legal terms",
  "currency": "KES",
  "due_date": "YYYY-MM-DD or null",
  "items": [
    {"description": "line text", "quantity": 1, "unit_price": 1000, "tax_rate": 16}
  ]
}
Use the business default tax rate when tax is not specified. Keep amounts numeric. Do not invent a customer name.
PROMPT;
    }

    private function userPrompt(
        InvoiceGeneration $generation,
        BusinessProfile $profile,
        ?Invoice $invoice,
        ?Contact $contact,
    ): string {
        $history = InvoiceGeneration::query()
            ->when(
                $generation->invoice_id !== null,
                fn ($q) => $q->where('invoice_id', $generation->invoice_id),
                fn ($q) => $q->whereNull('invoice_id')->where('requested_by_user_id', $generation->requested_by_user_id),
            )
            ->where('id', '<', $generation->id)
            ->where('status', AnalysisStatus::Completed)
            ->orderByDesc('id')
            ->limit(5)
            ->get(['id', 'prompt', 'result', 'completed_at']);

        $payload = [
            'operator_prompt' => $generation->prompt,
            'business' => [
                'name' => $profile->trading_name ?: $profile->legal_name,
                'currency' => $profile->currency,
                'default_tax_rate' => (float) $profile->default_tax_rate,
                'default_terms' => $profile->default_terms,
                'bank_details' => $profile->bank_details,
            ],
            'contact' => $contact === null ? null : [
                'id' => $contact->id,
                'full_name' => $contact->full_name,
                'business_name' => $contact->business_name,
                'email' => $contact->email,
                'phone' => $contact->primary_phone,
                'location' => $contact->location,
            ],
            'current_invoice' => $invoice === null ? null : [
                'number' => $invoice->number,
                'status' => $invoice->status->value,
                'issue_date' => $invoice->issue_date?->toDateString(),
                'due_date' => $invoice->due_date?->toDateString(),
                'currency' => $invoice->currency,
                'notes' => $invoice->notes,
                'terms_and_conditions' => $invoice->terms_and_conditions,
                'customer_address' => $invoice->customer_address,
                'items' => $invoice->items->map(fn ($item): array => [
                    'description' => $item->description,
                    'quantity' => (float) $item->quantity,
                    'unit_price' => (float) $item->unit_price,
                    'tax_rate' => (float) $item->tax_rate,
                ])->all(),
            ],
            'recent_generation_history' => $history->map(fn (InvoiceGeneration $row): array => [
                'id' => $row->id,
                'prompt' => $row->prompt,
                'result' => $row->result,
                'completed_at' => $row->completed_at?->toIso8601String(),
            ])->values()->all(),
            'today' => now()->toDateString(),
        ];

        return json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return array<string, mixed>
     */
    private function parse(string $raw, BusinessProfile $profile): array
    {
        $trimmed = trim($raw);

        if (preg_match('/\{.*\}/s', $trimmed, $matches) === 1) {
            $trimmed = $matches[0];
        }

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);

        $defaultTax = (float) $profile->default_tax_rate;
        $items = [];

        foreach (($decoded['items'] ?? []) as $item) {
            if (! is_array($item)) {
                continue;
            }

            $description = trim((string) ($item['description'] ?? ''));

            if ($description === '') {
                continue;
            }

            $items[] = [
                'description' => mb_substr($description, 0, 255),
                'quantity' => max(0.01, (float) ($item['quantity'] ?? 1)),
                'unit_price' => max(0, (float) ($item['unit_price'] ?? 0)),
                'tax_rate' => min(100, max(0, (float) ($item['tax_rate'] ?? $defaultTax))),
            ];
        }

        if ($items === []) {
            throw new \RuntimeException('The model returned no invoice line items.');
        }

        $currency = strtoupper(trim((string) ($decoded['currency'] ?? $profile->currency)));

        if (strlen($currency) !== 3) {
            $currency = $profile->currency;
        }

        $dueDate = $decoded['due_date'] ?? null;
        if (! is_string($dueDate) || $dueDate === '' || strtotime($dueDate) === false) {
            $dueDate = now()->addDays(14)->toDateString();
        }

        $terms = $decoded['terms_and_conditions'] ?? null;
        if (! is_string($terms) || trim($terms) === '') {
            $terms = $profile->default_terms;
        }

        return [
            'summary' => is_string($decoded['summary'] ?? null) ? mb_substr($decoded['summary'], 0, 500) : null,
            'customer_address' => is_string($decoded['customer_address'] ?? null)
                ? mb_substr($decoded['customer_address'], 0, 500)
                : null,
            'notes' => is_string($decoded['notes'] ?? null) ? mb_substr($decoded['notes'], 0, 4000) : null,
            'terms_and_conditions' => is_string($terms) ? mb_substr($terms, 0, 8000) : null,
            'currency' => $currency,
            'due_date' => $dueDate,
            'items' => $items,
        ];
    }
}
