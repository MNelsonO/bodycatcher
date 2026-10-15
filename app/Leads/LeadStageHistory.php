<?php

namespace App\Leads;

use App\Models\User;
use App\SalesPipelines\PipelineStage;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $lead_id
 * @property int|null $from_stage_id
 * @property int $to_stage_id
 * @property string|null $reason
 * @property int $actor_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read PipelineStage|null $fromStage
 * @property-read PipelineStage $toStage
 * @property-read User $actor
 */
#[Fillable(['lead_id', 'from_stage_id', 'to_stage_id', 'reason', 'actor_user_id'])]
class LeadStageHistory extends Model
{
    /**
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * @return BelongsTo<PipelineStage, $this>
     */
    public function fromStage(): BelongsTo
    {
        return $this->belongsTo(PipelineStage::class, 'from_stage_id');
    }

    /**
     * @return BelongsTo<PipelineStage, $this>
     */
    public function toStage(): BelongsTo
    {
        return $this->belongsTo(PipelineStage::class, 'to_stage_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
