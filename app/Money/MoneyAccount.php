<?php

namespace App\Money;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MoneyAccount extends Model
{
    protected $fillable = [
        'name',
        'institution',
        'account_type',
        'account_number',
        'currency',
        'balance',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<MoneyDebt, $this>
     */
    public function debts(): HasMany
    {
        return $this->hasMany(MoneyDebt::class);
    }

    /**
     * @return HasMany<MoneyRecurring, $this>
     */
    public function recurrings(): HasMany
    {
        return $this->hasMany(MoneyRecurring::class);
    }
}
