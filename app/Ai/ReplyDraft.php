<?php

namespace App\Ai;

use App\Conversations\Message;
use App\Leads\Lead;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $lead_id
 * @property int $lead_analysis_id
 * @property string $original_body
 * @property string $body
 * @property DraftStatus $status
 * @property int|null $approved_by_user_id
 * @property CarbonInterface|null $approved_at
 * @property int|null $message_id
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Lead $lead
 * @property-read LeadAnalysis $analysis
 * @property-read User|null $approver
 * @property-read Message|null $message
 */
#[Fillable([
    'lead_id',
    'lead_analysis_id',
    'original_body',
    'body',
    'status',
    'approved_by_user_id',
    'approved_at',
    'message_id',
])]
class ReplyDraft extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DraftStatus::class,
            'approved_at' => 'datetime',
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
     * @return BelongsTo<LeadAnalysis, $this>
     */
    public function analysis(): BelongsTo
    {
        return $this->belongsTo(LeadAnalysis::class, 'lead_analysis_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    /**
     * @return BelongsTo<Message, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }
}
