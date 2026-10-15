<?php

namespace App\Expenses;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Expense extends Model
{
    protected $fillable = [
        'created_by_user_id',
        'title',
        'category',
        'vendor',
        'amount',
        'tax_amount',
        'currency',
        'spent_on',
        'system_name',
        'notes',
        'receipt_path',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'spent_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function receiptUrl(): ?string
    {
        if (! filled($this->receipt_path)) {
            return null;
        }

        return Storage::disk('public')->url($this->receipt_path);
    }

    public function gross(): float
    {
        return round((float) $this->amount + (float) $this->tax_amount, 2);
    }
}
