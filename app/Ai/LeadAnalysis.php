<?php

namespace App\Ai;

use App\Leads\Lead;
use App\Models\User;
use App\SalesPipelines\PipelineStage;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $lead_id
 * @property int|null $requested_by_user_id
 * @property AnalysisStatus $status
 * @property string|null $summary
 * @property array<string, mixed>|null $extraction
 * @property int|null $score
 * @property list<string>|null $score_reasons
 * @property int|null $suggested_stage_id
 * @property string|null $stage_reason
 * @property string|null $stage_rejected_reason
 * @property list<int>|null $knowledge_article_ids
 * @property string|null $error
 * @property CarbonInterface|null $completed_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Lead $lead
 * @property-read User|null $requester
 * @property-read PipelineStage|null $suggestedStage
 * @property-read ReplyDraft|null $draft
 */
#[Fillable([
    'lead_id',
    'requested_by_user_id',
    'status',
    'summary',
    'extraction',
    'score',
    'score_reasons',
    'suggested_stage_id',
    'stage_reason',
    'stage_rejected_reason',
    'knowledge_article_ids',
    'error',
    'completed_at',
])]
class LeadAnalysis extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AnalysisStatus::class,
            'extraction' => 'array',
            'score_reasons' => 'array',
            'knowledge_article_ids' => 'array',
            'completed_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    /**
     * @return BelongsTo<PipelineStage, $this>
     */
    public function suggestedStage(): BelongsTo
    {
        return $this->belongsTo(PipelineStage::class, 'suggested_stage_id');
    }

    /**
     * @return HasOne<ReplyDraft, $this>
     */
    public function draft(): HasOne
    {
        return $this->hasOne(ReplyDraft::class);
    }
}
