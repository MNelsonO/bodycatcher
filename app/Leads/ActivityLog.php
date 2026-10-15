<?php

namespace App\Leads;

use App\Contacts\Contact;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property ActivityEvent $event
 * @property int|null $lead_id
 * @property int|null $contact_id
 * @property int|null $actor_user_id
 * @property array<string, mixed>|null $properties
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $actor
 */
#[Fillable(['event', 'lead_id', 'contact_id', 'actor_user_id', 'properties'])]
class ActivityLog extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event' => ActivityEvent::class,
            'properties' => 'array',
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
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
