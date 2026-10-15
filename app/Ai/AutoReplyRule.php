<?php

namespace App\Ai;

use App\SalesPipelines\PipelineStage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutoReplyRule extends Model
{
    protected $fillable = [
        'name',
        'is_active',
        'priority',
        'match_type',
        'match_value',
        'pipeline_stage_id',
        'reply_mode',
        'template_body',
        'require_open_window',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'require_open_window' => 'boolean',
            'priority' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<PipelineStage, $this>
     */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(PipelineStage::class, 'pipeline_stage_id');
    }
}
