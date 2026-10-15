<?php

namespace App\Leads;

use App\Contacts\Contact;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Desk record of a real touch with the client — phone call, meeting, note,
 * or visit. occurred_at is operator-controlled so backdating is allowed.
 *
 * @property int $id
 * @property int $lead_id
 * @property int $contact_id
 * @property int $user_id
 * @property InteractionType $type
 * @property InteractionDirection $direction
 * @property string $body
 * @property CarbonInterface $occurred_at
 * @property int|null $duration_minutes
 * @property string|null $outcome
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read User $author
 */
#[Fillable([
    'lead_id',
    'contact_id',
    'user_id',
    'type',
    'direction',
    'body',
    'occurred_at',
    'duration_minutes',
    'outcome',
])]
class LeadInteraction extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => InteractionType::class,
            'direction' => InteractionDirection::class,
            'occurred_at' => 'datetime',
            'duration_minutes' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
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
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
