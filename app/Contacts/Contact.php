<?php

namespace App\Contacts;

use App\Leads\Lead;
use Database\Factories\ContactFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $full_name
 * @property string $primary_phone
 * @property string|null $business_name
 * @property string|null $business_type
 * @property string|null $location
 * @property string|null $email
 * @property PreferredChannel $preferred_channel
 * @property ConsentStatus $consent_status
 * @property Carbon|null $opted_out_at
 * @property string|null $lead_source
 * @property int|null $merged_into_contact_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'full_name',
    'primary_phone',
    'business_name',
    'business_type',
    'location',
    'email',
    'preferred_channel',
    'consent_status',
    'opted_out_at',
    'lead_source',
    'merged_into_contact_id',
])]
#[UseFactory(ContactFactory::class)]
class Contact extends Model
{
    /** @use HasFactory<ContactFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'preferred_channel' => PreferredChannel::class,
            'consent_status' => ConsentStatus::class,
            'opted_out_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<ContactPhone, $this>
     */
    public function phones(): HasMany
    {
        return $this->hasMany(ContactPhone::class);
    }

    /**
     * @return HasMany<Lead, $this>
     */
    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function mergedInto(): BelongsTo
    {
        return $this->belongsTo(self::class, 'merged_into_contact_id');
    }

    public function survivor(): self
    {
        $current = $this;
        $guard = 0;

        while ($current->merged_into_contact_id !== null && $guard < 10) {
            $next = $current->mergedInto;

            if ($next === null) {
                break;
            }

            $current = $next;
            $guard++;
        }

        return $current;
    }
}
