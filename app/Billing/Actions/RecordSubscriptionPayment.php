<?php

namespace App\Billing\Actions;

use App\Billing\BillingSubscription;
use App\Billing\BusinessProfile;
use App\Billing\Services\InvoiceService;
use App\Billing\SubscriptionPayment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RecordSubscriptionPayment
{
    public function __construct(private InvoiceService $invoices) {}

    /**
     * @param  array{paid_on: string, payment_method_id?: int|null, amount?: float|string|null, reference?: string|null, notes?: string|null, create_invoice?: bool}  $data
     */
    public function handle(BillingSubscription $subscription, array $data, User $actor): SubscriptionPayment
    {
        return DB::transaction(function () use ($subscription, $data, $actor): SubscriptionPayment {
            $paidOn = Carbon::parse((string) $data['paid_on'])->toDateString();
            $amount = isset($data['amount']) && $data['amount'] !== null && $data['amount'] !== ''
                ? (float) $data['amount']
                : (float) $subscription->amount;
            $methodId = isset($data['payment_method_id']) && $data['payment_method_id']
                ? (int) $data['payment_method_id']
                : null;
            $profile = BusinessProfile::current();
            $invoiceId = null;

            if (($data['create_invoice'] ?? true) === true) {
                $invoice = $this->invoices->create([
                    'contact_id' => $subscription->contact_id,
                    'issue_date' => $paidOn,
                    'due_date' => $paidOn,
                    'currency' => $profile->currency,
                    'notes' => 'Payment recorded on monthly plan: '.$subscription->label,
                    'customer_address' => $subscription->contact->location,
                ], [[
                    'description' => $subscription->label.($subscription->product ? ' · '.$subscription->product->name : ''),
                    'quantity' => 1,
                    'unit_price' => $amount,
                    'tax_rate' => $subscription->tax_rate,
                ]], $actor);

                $this->invoices->markSent($invoice);
                $this->invoices->markPaid($invoice, $paidOn, $methodId);
                $invoiceId = $invoice->id;
                $subscription->last_invoice_id = $invoice->id;
            }

            $payment = SubscriptionPayment::query()->create([
                'billing_subscription_id' => $subscription->id,
                'contact_id' => $subscription->contact_id,
                'payment_method_id' => $methodId,
                'recorded_by_user_id' => $actor->id,
                'invoice_id' => $invoiceId,
                'amount' => $amount,
                'currency' => $profile->currency,
                'paid_on' => $paidOn,
                'reference' => filled($data['reference'] ?? null) ? trim((string) $data['reference']) : null,
                'notes' => filled($data['notes'] ?? null) ? trim((string) $data['notes']) : null,
            ]);

            $subscription->last_paid_on = $paidOn;
            $subscription->last_payment_method_id = $methodId;

            if ($subscription->cadence === 'monthly') {
                $anchor = $subscription->next_due_on ?? Carbon::parse($paidOn);
                // Advance from the later of next_due_on or paid_on so follow-ups stay ahead.
                $from = $anchor->lt(Carbon::parse($paidOn)) ? Carbon::parse($paidOn) : $anchor->copy();
                $subscription->next_due_on = $from->addMonth();
            }

            $subscription->save();

            return $payment;
        });
    }
}
