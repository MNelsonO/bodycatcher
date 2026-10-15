<?php

namespace App\Billing;

use App\Contacts\Contact;
use App\Settings\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingSubscription extends Model
{
    protected $fillable = [
        'contact_id',
        'product_id',
        'label',
        'amount',
        'tax_rate',
        'cadence',
        'next_due_on',
        'last_paid_on',
        'last_payment_method_id',
        'status',
        'alert_days_before',
        'notes',
        'last_invoice_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'next_due_on' => 'date',
            'last_paid_on' => 'date',
            'alert_days_before' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<PaymentMethod, $this>
     */
    public function lastPaymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'last_payment_method_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<SubscriptionPayment, $this>
     */
    public function payments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SubscriptionPayment::class, 'billing_subscription_id')->orderByDesc('paid_on')->orderByDesc('id');
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function lastInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'last_invoice_id');
    }

    public function isDueSoon(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        $today = now()->startOfDay();
        $due = $this->next_due_on?->startOfDay();

        if ($due === null) {
            return false;
        }

        return $due->lte($today->copy()->addDays($this->alert_days_before));
    }

    public function isOverdue(): bool
    {
        return $this->status === 'active'
            && $this->next_due_on !== null
            && $this->next_due_on->lt(now()->startOfDay());
    }
}
