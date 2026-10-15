<?php

namespace App\Http\Controllers;

use App\Billing\BillingSubscription;
use App\Billing\BusinessProfile;
use App\Billing\Services\BillingAlertService;
use App\Billing\Services\InvoiceService;
use App\Contacts\Contact;
use App\Http\Requests\BillingSubscriptionRequest;
use App\Models\User;
use App\Settings\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BillingSubscriptionController extends Controller
{
    public function index(BillingAlertService $alerts): Response
    {
        $subs = BillingSubscription::query()
            ->with(['contact:id,full_name,business_name', 'product:id,name'])
            ->orderBy('next_due_on')
            ->get()
            ->map(fn (BillingSubscription $sub): array => [
                'id' => $sub->id,
                'label' => $sub->label,
                'contact_id' => $sub->contact_id,
                'contact_name' => $sub->contact->full_name,
                'business_name' => $sub->contact->business_name,
                'product_id' => $sub->product_id,
                'product_name' => $sub->product?->name,
                'amount' => (float) $sub->amount,
                'tax_rate' => (float) $sub->tax_rate,
                'cadence' => $sub->cadence,
                'next_due_on' => $sub->next_due_on?->toDateString(),
                'status' => $sub->status,
                'alert_days_before' => $sub->alert_days_before,
                'notes' => $sub->notes,
                'due_soon' => $sub->isDueSoon(),
                'overdue' => $sub->isOverdue(),
            ]);

        return Inertia::render('billing/subscriptions/index', [
            'subscriptions' => $subs,
            'alerts' => $alerts->snapshot(),
            'contacts' => Contact::query()
                ->whereNull('merged_into_contact_id')
                ->orderBy('full_name')
                ->get(['id', 'full_name', 'business_name']),
            'products' => Product::query()
                ->where('is_active', true)
                ->whereNull('archived_at')
                ->orderBy('name')
                ->get(['id', 'name']),
            'defaults' => [
                'tax_rate' => (float) BusinessProfile::current()->default_tax_rate,
                'next_due_on' => now()->addMonth()->toDateString(),
            ],
        ]);
    }

    public function store(BillingSubscriptionRequest $request): RedirectResponse
    {
        BillingSubscription::query()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Monthly customer saved.']);

        return redirect()->route('billing.subscriptions.index');
    }

    public function update(BillingSubscriptionRequest $request, BillingSubscription $subscription): RedirectResponse
    {
        $subscription->fill($request->validated());
        $subscription->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Monthly customer updated.']);

        return redirect()->route('billing.subscriptions.index');
    }

    public function destroy(BillingSubscription $subscription): RedirectResponse
    {
        $subscription->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Monthly customer removed.']);

        return redirect()->route('billing.subscriptions.index');
    }

    public function invoiceNow(Request $request, BillingSubscription $subscription, InvoiceService $invoices): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $profile = BusinessProfile::current();

        $invoice = $invoices->create([
            'contact_id' => $subscription->contact_id,
            'issue_date' => now()->toDateString(),
            'due_date' => $subscription->next_due_on?->toDateString() ?? now()->toDateString(),
            'currency' => $profile->currency,
            'notes' => 'Generated from monthly plan: '.$subscription->label,
            'customer_address' => $subscription->contact->location,
        ], [[
            'description' => $subscription->label.($subscription->product ? ' · '.$subscription->product->name : ''),
            'quantity' => 1,
            'unit_price' => $subscription->amount,
            'tax_rate' => $subscription->tax_rate,
        ]], $actor);

        $invoices->markSent($invoice);

        $subscription->last_invoice_id = $invoice->id;
        if ($subscription->cadence === 'monthly') {
            $subscription->next_due_on = ($subscription->next_due_on ?? now())->addMonth();
        }
        $subscription->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Invoice '.$invoice->number.' created from monthly plan.']);

        return redirect()->route('invoices.show', $invoice);
    }
}
