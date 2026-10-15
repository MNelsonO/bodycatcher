<?php

namespace App\SalesPipelines;

use Database\Factories\PipelineStageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $pipeline_id
 * @property string $name
 * @property StageKind $kind
 * @property int $position
 * @property int|null $default_follow_up_delay_hours
 * @property Carbon|null $archived_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['pipeline_id', 'name', 'kind', 'position', 'default_follow_up_delay_hours', 'archived_at'])]
#[UseFactory(PipelineStageFactory::class)]
class PipelineStage extends Model
{
    /** @use HasFactory<PipelineStageFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => StageKind::class,
            'position' => 'integer',
            'default_follow_up_delay_hours' => 'integer',
            'archived_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Pipeline, $this>
     */
    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }

    /**
     * @return HasMany<PipelineStageTransition, $this>
     */
    public function outgoingTransitions(): HasMany
    {
        return $this->hasMany(PipelineStageTransition::class, 'from_stage_id');
    }
}
