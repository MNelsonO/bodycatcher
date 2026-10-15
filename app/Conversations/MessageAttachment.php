<?php

namespace App\Conversations;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $message_id
 * @property string $provider_media_id
 * @property string|null $mime_type
 * @property string|null $caption
 */
#[Fillable(['message_id', 'provider_media_id', 'mime_type', 'caption'])]
class MessageAttachment extends Model
{
    /**
     * @return BelongsTo<Message, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }
}
