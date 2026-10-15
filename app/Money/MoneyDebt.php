<?php

namespace App\Money;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MoneyDebt extends Model
{
    protected $fillable = [
        'direction',
        'party_name',
        'title',
        'amount',
        'amount_paid',
        'currency',
        'due_on',
        'status',
        'money_account_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'due_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<MoneyAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(MoneyAccount::class, 'money_account_id');
    }

    public function remaining(): float
    {
        return round(max(0, (float) $this->amount - (float) $this->amount_paid), 2);
    }

    public function refreshStatus(): void
    {
        $remaining = $this->remaining();

        $this->status = match (true) {
            $remaining <= 0 => 'settled',
            (float) $this->amount_paid > 0 => 'partial',
            default => 'open',
        };
    }
}
