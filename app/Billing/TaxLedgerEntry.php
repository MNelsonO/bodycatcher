<?php

namespace App\Billing;

use App\Contacts\Contact;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxLedgerEntry extends Model
{
    protected $fillable = [
        'source',
        'source_id',
        'contact_id',
        'description',
        'net_amount',
        'tax_amount',
        'gross_amount',
        'occurred_on',
        'system_name',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'net_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'gross_amount' => 'decimal:2',
            'occurred_on' => 'date',
            'meta' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
