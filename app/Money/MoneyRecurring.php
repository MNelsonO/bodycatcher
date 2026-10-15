<?php

namespace App\Money;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\CarbonInterface;

class MoneyRecurring extends Model
{
    protected $fillable = [
        'direction',
        'title',
        'party_name',
        'amount',
        'currency',
        'cadence',
        'day_of_month',
        'next_due_on',
        'status',
        'money_account_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'day_of_month' => 'integer',
            'next_due_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<MoneyAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(MoneyAccount::class, 'money_account_id');
    }

    public function isDueSoon(int $withinDays = 7): bool
    {
        if ($this->status !== 'active' || $this->next_due_on === null) {
            return false;
        }

        return $this->next_due_on->lte(now()->startOfDay()->addDays($withinDays));
    }

    public function isOverdue(): bool
    {
        return $this->status === 'active'
            && $this->next_due_on !== null
            && $this->next_due_on->lt(now()->startOfDay());
    }

    public function advanceNextDue(): void
    {
        $current = $this->next_due_on?->copy() ?? now()->startOfDay();

        $this->next_due_on = match ($this->cadence) {
            'weekly' => $current->addWeek(),
            'yearly' => $current->addYear(),
            default => $this->nextMonthlyDue($current),
        };
    }

    private function nextMonthlyDue(CarbonInterface $from): CarbonInterface
    {
        $day = min(max((int) ($this->day_of_month ?: $from->day), 1), 28);
        $next = $from->copy()->addMonthNoOverflow()->day($day);

        return $next;
    }
}
