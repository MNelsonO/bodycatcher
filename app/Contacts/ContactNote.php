<?php

namespace App\Contacts;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Desk notes on a client. The live assistant and lead analysis read these
 * so they know the conversation area and any standing context.
 *
 * @property int $id
 * @property int $contact_id
 * @property int $user_id
 * @property string $body
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read User $author
 */
#[Fillable(['contact_id', 'user_id', 'body'])]
class ContactNote extends Model
{
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
