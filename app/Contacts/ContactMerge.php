<?php

namespace App\Contacts;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $survivor_contact_id
 * @property int $merged_contact_id
 * @property int $actor_user_id
 * @property array<string, mixed>|null $snapshot
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['survivor_contact_id', 'merged_contact_id', 'actor_user_id', 'snapshot'])]
class ContactMerge extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function survivor(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'survivor_contact_id');
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function merged(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'merged_contact_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
