<?php

namespace App\Billing;

use App\Contacts\Contact;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A recorded payout against an active monthly subscription.
 *
 * @property int $id
 * @property int $billing_subscription_id
 * @property int $contact_id
 * @property int|null $payment_method_id
 * @property int|null $recorded_by_user_id
 * @property int|null $invoice_id
 * @property string $amount
 * @property string $currency
 * @property CarbonInterface $paid_on
 * @property string|null $reference
 * @property string|null $notes
 */
#[Fillable([
    'billing_subscription_id',
    'contact_id',
    'payment_method_id',
    'recorded_by_user_id',
    'invoice_id',
    'amount',
    'currency',
    'paid_on',
    'reference',
    'notes',
])]
class SubscriptionPayment extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<BillingSubscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(BillingSubscription::class, 'billing_subscription_id');
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * @return BelongsTo<PaymentMethod, $this>
     */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
