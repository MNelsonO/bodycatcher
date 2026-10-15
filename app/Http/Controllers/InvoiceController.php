<?php

namespace App\Http\Controllers;

use App\Billing\BusinessProfile;
use App\Billing\Invoice;
use App\Billing\InvoiceStatus;
use App\Billing\PaymentMethod;
use App\Billing\Services\BillingAlertService;
use App\Billing\Services\InvoiceService;
use App\Contacts\Contact;
use App\Http\Requests\InvoiceRequest;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class InvoiceController extends Controller
{
    public function index(Request $request, BillingAlertService $alerts): InertiaResponse
    {
        $status = $request->string('status')->toString();

        $query = Invoice::query()->with('contact:id,full_name,business_name')->orderByDesc('id');

        if ($status !== '' && $status !== 'all') {
            $query->where('status', $status);
        }

        return Inertia::render('billing/invoices/index', [
            'invoices' => $query->paginate(20)->withQueryString()->through(fn (Invoice $invoice): array => [
                'id' => $invoice->id,
                'number' => $invoice->number,
                'contact_name' => $invoice->contact->full_name,
                'business_name' => $invoice->contact->business_name,
                'status' => $invoice->status->value,
                'issue_date' => $invoice->issue_date?->toDateString(),
                'due_date' => $invoice->due_date?->toDateString(),
                'total' => (float) $invoice->total,
                'currency' => $invoice->currency,
                'share_token' => $invoice->share_token,
            ]),
            'filters' => ['status' => $status],
            'alerts' => $alerts->snapshot(),
        ]);
    }

    public function create(): InertiaResponse
    {
        $profile = BusinessProfile::current();

        return Inertia::render('billing/invoices/create', [
            'contacts' => Contact::query()
                ->whereNull('merged_into_contact_id')
                ->orderBy('full_name')
                ->get(['id', 'full_name', 'business_name', 'email', 'location']),
            'defaults' => [
                'issue_date' => now()->toDateString(),
                'due_date' => now()->addDays(14)->toDateString(),
                'currency' => $profile->currency,
                'tax_rate' => (float) $profile->default_tax_rate,
            ],
        ]);
    }

    public function store(InvoiceRequest $request, InvoiceService $service): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $invoice = $service->create($request->validated(), $request->input('items', []), $actor);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Invoice '.$invoice->number.' created.']);

        return redirect()->route('invoices.show', $invoice);
    }

    public function show(Invoice $invoice): InertiaResponse
    {
        $invoice->refreshOpenStatus();
        $invoice->load(['contact', 'items', 'creator', 'paymentMethod']);
        $profile = BusinessProfile::current();

        return Inertia::render('billing/invoices/show', [
            'invoice' => $this->payload($invoice),
            'business' => $this->businessPayload($profile),
            'share_url' => url('/share/invoices/'.$invoice->share_token),
            'payment_methods' => PaymentMethod::query()->active()->get(['id', 'name']),
            'defaults' => [
                'paid_on' => now()->toDateString(),
            ],
        ]);
    }

    public function update(InvoiceRequest $request, Invoice $invoice, InvoiceService $service): RedirectResponse
    {
        $service->update($invoice, $request->validated(), $request->input('items', []));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Invoice updated.']);

        return redirect()->route('invoices.show', $invoice);
    }

    public function markPaid(\App\Http\Requests\MarkInvoicePaidRequest $request, Invoice $invoice, InvoiceService $service): RedirectResponse
    {
        $paidOn = $request->input('paid_on');
        $methodId = $request->input('payment_method_id');

        $service->markPaid(
            $invoice,
            is_string($paidOn) && $paidOn !== '' ? $paidOn : null,
            is_numeric($methodId) ? (int) $methodId : null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Invoice marked paid. Tax logged.']);

        return redirect()->route('invoices.show', $invoice);
    }

    public function markUnpaid(Invoice $invoice, InvoiceService $service): RedirectResponse
    {
        $service->markUnpaid($invoice);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Invoice marked unpaid.']);

        return redirect()->route('invoices.show', $invoice);
    }

    public function markSent(Invoice $invoice, InvoiceService $service): RedirectResponse
    {
        $service->markSent($invoice);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Invoice marked sent / unpaid.']);

        return redirect()->route('invoices.show', $invoice);
    }

    public function pdf(Invoice $invoice): Response
    {
        $invoice->load(['contact', 'items']);
        $profile = BusinessProfile::current();

        return Pdf::loadView('billing.invoice-pdf', [
            'invoice' => $invoice,
            'business' => $profile,
            'logoPath' => $profile->logo_path
                ? storage_path('app/public/'.$profile->logo_path)
                : null,
        ])->download($invoice->number.'.pdf');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Invoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'number' => $invoice->number,
            'status' => $invoice->status->value,
            'issue_date' => $invoice->issue_date?->toDateString(),
            'due_date' => $invoice->due_date?->toDateString(),
            'currency' => $invoice->currency,
            'subtotal' => (float) $invoice->subtotal,
            'tax_total' => (float) $invoice->tax_total,
            'total' => (float) $invoice->total,
            'notes' => $invoice->notes,
            'customer_address' => $invoice->customer_address,
            'share_token' => $invoice->share_token,
            'paid_at' => $invoice->paid_at?->toIso8601String(),
            'paid_on' => $invoice->paid_at?->toDateString(),
            'payment_method' => $invoice->paymentMethod?->name,
            'payment_method_id' => $invoice->payment_method_id,
            'sent_at' => $invoice->sent_at?->toIso8601String(),
            'contact' => [
                'id' => $invoice->contact->id,
                'full_name' => $invoice->contact->full_name,
                'business_name' => $invoice->contact->business_name,
                'email' => $invoice->contact->email,
                'primary_phone' => $invoice->contact->primary_phone,
                'location' => $invoice->contact->location,
            ],
            'items' => $invoice->items->map(fn ($item): array => [
                'id' => $item->id,
                'description' => $item->description,
                'quantity' => (float) $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'tax_rate' => (float) $item->tax_rate,
                'line_subtotal' => (float) $item->line_subtotal,
                'line_tax' => (float) $item->line_tax,
                'line_total' => (float) $item->line_total,
            ])->all(),
            'creator_name' => $invoice->creator?->name,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function businessPayload(BusinessProfile $profile): array
    {
        return [
            'legal_name' => $profile->legal_name,
            'trading_name' => $profile->trading_name,
            'address_lines' => $profile->addressLines(),
            'phone' => $profile->phone,
            'email' => $profile->email,
            'tax_id' => $profile->tax_id,
            'bank_details' => $profile->bank_details,
            'logo_url' => $profile->logoUrl(),
            'currency' => $profile->currency,
            'default_tax_rate' => (float) $profile->default_tax_rate,
        ];
    }
}
