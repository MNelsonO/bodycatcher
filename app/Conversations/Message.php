<?php

namespace App\Conversations;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $conversation_id
 * @property MessageDirection $direction
 * @property string $body
 * @property Carbon $sent_at
 * @property int $logged_by_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $loggedBy
 * @property-read Conversation $conversation
 */
#[Fillable(['conversation_id', 'direction', 'body', 'sent_at', 'logged_by_user_id'])]
class Message extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'direction' => MessageDirection::class,
            'sent_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function loggedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'logged_by_user_id');
    }
}
