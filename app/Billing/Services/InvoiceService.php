<?php

namespace App\Billing\Services;

use App\Billing\Invoice;
use App\Billing\InvoiceItem;
use App\Billing\InvoiceStatus;
use App\Billing\TaxLedgerEntry;
use App\Models\User;
use App\Settings\DisplayClock;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    public function __construct(private DisplayClock $clock) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array{description: string, quantity: float|int|string, unit_price: float|int|string, tax_rate?: float|int|string}>  $items
     */
    public function create(array $data, array $items, User $actor): Invoice
    {
        return DB::transaction(function () use ($data, $items, $actor): Invoice {
            $invoice = Invoice::query()->create([
                'number' => Invoice::nextNumber(),
                'contact_id' => (int) $data['contact_id'],
                'created_by_user_id' => $actor->id,
                'status' => InvoiceStatus::Draft,
                'issue_date' => $data['issue_date'],
                'due_date' => $data['due_date'],
                'currency' => $data['currency'] ?? 'KES',
                'notes' => $data['notes'] ?? null,
                'terms_and_conditions' => $data['terms_and_conditions'] ?? null,
                'customer_address' => $data['customer_address'] ?? null,
            ]);

            $this->syncItems($invoice, $items);
            $this->recalculate($invoice);

            return $invoice->fresh(['items', 'contact']) ?? $invoice;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array{description: string, quantity: float|int|string, unit_price: float|int|string, tax_rate?: float|int|string}>  $items
     */
    public function update(Invoice $invoice, array $data, array $items): Invoice
    {
        return DB::transaction(function () use ($invoice, $data, $items): Invoice {
            if ($invoice->status === InvoiceStatus::Paid || $invoice->status === InvoiceStatus::Void) {
                return $invoice;
            }

            $invoice->fill([
                'contact_id' => (int) $data['contact_id'],
                'issue_date' => $data['issue_date'],
                'due_date' => $data['due_date'],
                'currency' => $data['currency'] ?? $invoice->currency,
                'notes' => $data['notes'] ?? null,
                'terms_and_conditions' => $data['terms_and_conditions'] ?? null,
                'customer_address' => $data['customer_address'] ?? null,
            ]);
            $invoice->save();

            $invoice->items()->delete();
            $this->syncItems($invoice, $items);
            $this->recalculate($invoice);

            return $invoice->fresh(['items', 'contact']) ?? $invoice;
        });
    }

    public function markSent(Invoice $invoice): Invoice
    {
        if ($invoice->status === InvoiceStatus::Draft) {
            $invoice->status = InvoiceStatus::Unpaid;
            $invoice->sent_at = now();
            $invoice->save();
            $invoice->refreshOpenStatus();
        }

        return $invoice;
    }

    public function markPaid(Invoice $invoice, ?string $paidOn = null, ?int $paymentMethodId = null): Invoice
    {
        return DB::transaction(function () use ($invoice, $paidOn, $paymentMethodId): Invoice {
            $invoice->status = InvoiceStatus::Paid;
            $invoice->paid_at = $paidOn !== null && $paidOn !== ''
                ? Carbon::parse($paidOn, $this->clock->timezone())->endOfDay()->utc()
                : now();
            $invoice->payment_method_id = $paymentMethodId;
            $invoice->save();

            TaxLedgerEntry::query()->updateOrCreate(
                [
                    'source' => 'invoice',
                    'source_id' => $invoice->id,
                    'system_name' => 'billing',
                ],
                [
                    'contact_id' => $invoice->contact_id,
                    'description' => 'Tax on invoice '.$invoice->number,
                    'net_amount' => $invoice->subtotal,
                    'tax_amount' => $invoice->tax_total,
                    'gross_amount' => $invoice->total,
                    'occurred_on' => $invoice->paid_at
                        ? $invoice->paid_at->timezone($this->clock->timezone())->toDateString()
                        : $this->clock->today(),
                    'meta' => [
                        'invoice_number' => $invoice->number,
                        'currency' => $invoice->currency,
                        'payment_method_id' => $paymentMethodId,
                    ],
                ],
            );

            return $invoice;
        });
    }

    public function markUnpaid(Invoice $invoice): Invoice
    {
        return DB::transaction(function () use ($invoice): Invoice {
            $invoice->status = InvoiceStatus::Unpaid;
            $invoice->paid_at = null;
            $invoice->payment_method_id = null;
            $invoice->save();
            $invoice->refreshOpenStatus();

            TaxLedgerEntry::query()
                ->where('source', 'invoice')
                ->where('source_id', $invoice->id)
                ->delete();

            return $invoice;
        });
    }

    /**
     * @param  list<array{description: string, quantity: float|int|string, unit_price: float|int|string, tax_rate?: float|int|string}>  $items
     */
    private function syncItems(Invoice $invoice, array $items): void
    {
        $position = 1;

        foreach ($items as $item) {
            $qty = (float) $item['quantity'];
            $price = (float) $item['unit_price'];
            $rate = (float) ($item['tax_rate'] ?? 0);
            $subtotal = round($qty * $price, 2);
            $tax = round($subtotal * ($rate / 100), 2);

            InvoiceItem::query()->create([
                'invoice_id' => $invoice->id,
                'description' => $item['description'],
                'quantity' => $qty,
                'unit_price' => $price,
                'tax_rate' => $rate,
                'line_subtotal' => $subtotal,
                'line_tax' => $tax,
                'line_total' => $subtotal + $tax,
                'position' => $position++,
            ]);
        }
    }

    private function recalculate(Invoice $invoice): void
    {
        $invoice->load('items');
        $invoice->subtotal = round((float) $invoice->items->sum('line_subtotal'), 2);
        $invoice->tax_total = round((float) $invoice->items->sum('line_tax'), 2);
        $invoice->total = round((float) $invoice->items->sum('line_total'), 2);
        $invoice->save();
    }
}
