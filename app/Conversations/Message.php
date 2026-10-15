<?php

namespace App\Conversations;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $conversation_id
 * @property MessageDirection $direction
 * @property MessageChannel $channel
 * @property MessageOrigin $origin
 * @property MessageStatus $status
 * @property string $body
 * @property Carbon $sent_at
 * @property int|null $logged_by_user_id
 * @property string|null $idempotency_key
 * @property string|null $provider_message_id
 * @property array<string, mixed>|null $provider_response
 * @property string|null $refusal_reason
 * @property bool $retryable
 * @property int $send_attempts
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $loggedBy
 * @property-read Conversation $conversation
 */
#[Fillable([
    'conversation_id',
    'direction',
    'channel',
    'origin',
    'status',
    'body',
    'sent_at',
    'logged_by_user_id',
    'idempotency_key',
    'provider_message_id',
    'provider_response',
    'refusal_reason',
    'retryable',
    'send_attempts',
])]
class Message extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'direction' => MessageDirection::class,
            'channel' => MessageChannel::class,
            'origin' => MessageOrigin::class,
            'status' => MessageStatus::class,
            'sent_at' => 'datetime',
            'provider_response' => 'array',
            'retryable' => 'boolean',
            'send_attempts' => 'integer',
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

    /**
     * @return HasMany<MessageAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(MessageAttachment::class);
    }

    /**
     * @return HasMany<MessageStatusEvent, $this>
     */
    public function statusEvents(): HasMany
    {
        return $this->hasMany(MessageStatusEvent::class)->orderBy('id');
    }
}
