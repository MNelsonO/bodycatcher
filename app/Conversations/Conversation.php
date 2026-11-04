<?php

namespace App\Conversations;

use App\Contacts\Contact;
use App\Leads\Lead;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $lead_id
 * @property int $contact_id
 * @property string|null $topic
 * @property string|null $topic_summary
 * @property string|null $memory_summary
 * @property int|null $focused_product_id
 * @property Carbon|null $topic_updated_at
 * @property CarbonInterface|null $last_message_at
 * @property CarbonInterface|null $last_read_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['lead_id', 'contact_id', 'topic', 'topic_summary', 'memory_summary', 'focused_product_id', 'topic_updated_at', 'last_message_at', 'last_read_at'])]
class Conversation extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'last_read_at' => 'datetime',
        ];
    }

    /**
     * @return HasOne<Message, $this>
     */
    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->ofMany(
            ['sent_at' => 'max', 'id' => 'max'],
            fn ($query) => $query->where('body', '!=', ''),
        );
    }

    /**
     * Adds unread_count: inbound messages newer than the desk's last look.
     *
     * @param  Builder<Conversation>  $query
     * @return Builder<Conversation>
     */
    public function scopeWithUnreadCount(Builder $query): Builder
    {
        return $query->addSelect([
            'unread_count' => Message::query()
                ->selectRaw('count(*)')
                ->whereColumn('messages.conversation_id', 'conversations.id')
                ->where('messages.direction', MessageDirection::Inbound->value)
                ->where('messages.body', '!=', '')
                ->where(function ($inner): void {
                    $inner->whereNull('conversations.last_read_at')
                        ->orWhereColumn('messages.sent_at', '>', 'conversations.last_read_at');
                }),
        ]);
    }

    public function markRead(): void
    {
        $this->last_read_at = now();
        $this->saveQuietly();
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
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        // Empty messages are old WhatsApp Status posts with no text; they
        // carry nothing to read, so chats, the lead page and the AI skip them.
        return $this->hasMany(Message::class)
            ->where('body', '!=', '')
            ->orderBy('sent_at')
            ->orderBy('id');
    }
}
