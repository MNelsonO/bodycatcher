<?php

namespace App\Billing;

use App\Contacts\Contact;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Invoice extends Model
{
    protected $fillable = [
        'number',
        'contact_id',
        'created_by_user_id',
        'status',
        'issue_date',
        'due_date',
        'currency',
        'subtotal',
        'tax_total',
        'total',
        'notes',
        'customer_address',
        'share_token',
        'paid_at',
        'payment_method_id',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'issue_date' => 'date',
            'due_date' => 'date',
            'subtotal' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<PaymentMethod, $this>
     */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    protected static function booted(): void
    {
        static::creating(function (Invoice $invoice): void {
            if ($invoice->share_token === null || $invoice->share_token === '') {
                $invoice->share_token = Str::random(40);
            }
        });
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return HasMany<InvoiceItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('position')->orderBy('id');
    }

    public static function nextNumber(): string
    {
        $year = now()->format('Y');
        $latest = self::query()
            ->where('number', 'like', "INV-{$year}-%")
            ->orderByDesc('number')
            ->value('number');

        $seq = 1;

        if (is_string($latest) && preg_match('/INV-\d{4}-(\d+)$/', $latest, $matches) === 1) {
            $seq = ((int) $matches[1]) + 1;
        }

        return sprintf('INV-%s-%04d', $year, $seq);
    }

    public function refreshOpenStatus(): void
    {
        if ($this->status === InvoiceStatus::Paid || $this->status === InvoiceStatus::Void || $this->status === InvoiceStatus::Draft) {
            return;
        }

        if ($this->due_date !== null && $this->due_date->lt(now()->startOfDay())) {
            $this->status = InvoiceStatus::Overdue;
            $this->save();
        }
    }
}
