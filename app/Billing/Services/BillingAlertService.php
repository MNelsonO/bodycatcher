<?php

namespace App\Billing\Services;

use App\Billing\BillingSubscription;
use App\Billing\Invoice;
use App\Billing\InvoiceStatus;
use Illuminate\Support\Collection;

class BillingAlertService
{
    /**
     * @return array{overdue_invoices: list<array<string, mixed>>, due_subscriptions: list<array<string, mixed>>, unpaid_invoices: list<array<string, mixed>>}
     */
    public function snapshot(): array
    {
        Invoice::query()
            ->whereIn('status', [InvoiceStatus::Sent->value, InvoiceStatus::Unpaid->value])
            ->whereDate('due_date', '<', now()->toDateString())
            ->update(['status' => InvoiceStatus::Overdue->value]);

        $overdue = Invoice::query()
            ->with('contact:id,full_name,business_name')
            ->where('status', InvoiceStatus::Overdue->value)
            ->orderBy('due_date')
            ->limit(50)
            ->get();

        $unpaid = Invoice::query()
            ->with('contact:id,full_name,business_name')
            ->whereIn('status', [InvoiceStatus::Unpaid->value, InvoiceStatus::Sent->value])
            ->orderBy('due_date')
            ->limit(50)
            ->get();

        $subs = BillingSubscription::query()
            ->with(['contact:id,full_name,business_name', 'product:id,name'])
            ->where('status', 'active')
            ->orderBy('next_due_on')
            ->get()
            ->filter(fn (BillingSubscription $sub): bool => $sub->isDueSoon() || $sub->isOverdue());

        return [
            'overdue_invoices' => $this->invoiceRows($overdue),
            'unpaid_invoices' => $this->invoiceRows($unpaid),
            'due_subscriptions' => $subs->values()->map(fn (BillingSubscription $sub): array => [
                'id' => $sub->id,
                'label' => $sub->label,
                'contact_name' => $sub->contact->full_name,
                'business_name' => $sub->contact->business_name,
                'product_name' => $sub->product?->name,
                'amount' => (float) $sub->amount,
                'next_due_on' => $sub->next_due_on?->toDateString(),
                'overdue' => $sub->isOverdue(),
            ])->all(),
        ];
    }

    /**
     * @param  Collection<int, Invoice>  $invoices
     * @return list<array<string, mixed>>
     */
    private function invoiceRows(Collection $invoices): array
    {
        return $invoices->map(fn (Invoice $invoice): array => [
            'id' => $invoice->id,
            'number' => $invoice->number,
            'contact_name' => $invoice->contact->full_name,
            'business_name' => $invoice->contact->business_name,
            'total' => (float) $invoice->total,
            'currency' => $invoice->currency,
            'due_date' => $invoice->due_date?->toDateString(),
            'status' => $invoice->status->value,
        ])->all();
    }
}
