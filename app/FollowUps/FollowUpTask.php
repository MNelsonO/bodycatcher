<?php

namespace App\FollowUps;

use App\Conversations\Message;
use App\Leads\Lead;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $lead_id
 * @property int|null $automation_rule_id
 * @property string $idempotency_key
 * @property FollowUpAction $action
 * @property string $body
 * @property CarbonInterface $due_at
 * @property CarbonInterface|null $anchor_at
 * @property bool $cancel_on_reply
 * @property FollowUpTaskStatus $status
 * @property string|null $held_reason
 * @property string|null $failure_reason
 * @property int|null $message_id
 * @property CarbonInterface|null $executed_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Lead $lead
 * @property-read AutomationRule|null $rule
 * @property-read Message|null $message
 */
#[Fillable([
    'lead_id',
    'automation_rule_id',
    'idempotency_key',
    'action',
    'body',
    'due_at',
    'anchor_at',
    'cancel_on_reply',
    'status',
    'held_reason',
    'failure_reason',
    'message_id',
    'executed_at',
])]
class FollowUpTask extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action' => FollowUpAction::class,
            'due_at' => 'datetime',
            'anchor_at' => 'datetime',
            'cancel_on_reply' => 'boolean',
            'status' => FollowUpTaskStatus::class,
            'executed_at' => 'datetime',
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
     * @return BelongsTo<AutomationRule, $this>
     */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(AutomationRule::class, 'automation_rule_id');
    }

    /**
     * @return BelongsTo<Message, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }
}
