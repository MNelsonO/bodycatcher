<?php

namespace App\Ai;

use App\Calendar\CalendarActivity;
use App\Conversations\Conversation;
use App\Leads\Lead;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $lead_id
 * @property int|null $conversation_id
 * @property string $status
 * @property string $source
 * @property string $reason
 * @property string|null $inbound_snippet
 * @property string|null $suggested_reply
 * @property string|null $operator_correction
 * @property int|null $calendar_activity_id
 * @property Carbon|null $notified_at
 * @property int|null $resolved_by_user_id
 * @property Carbon|null $resolved_at
 */
#[Fillable([
    'lead_id',
    'conversation_id',
    'status',
    'source',
    'reason',
    'inbound_snippet',
    'suggested_reply',
    'operator_correction',
    'calendar_activity_id',
    'notified_at',
    'resolved_by_user_id',
    'resolved_at',
])]
class OperatorEscalation extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'notified_at' => 'datetime',
            'resolved_at' => 'datetime',
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
     * @return BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * @return BelongsTo<CalendarActivity, $this>
     */
    public function calendarActivity(): BelongsTo
    {
        return $this->belongsTo(CalendarActivity::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }
}
