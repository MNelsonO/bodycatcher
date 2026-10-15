<?php

namespace App\Leads;

use App\Contacts\Contact;
use App\Conversations\Conversation;
use App\SalesPipelines\Pipeline;
use App\SalesPipelines\PipelineStage;
use App\SalesPipelines\StageKind;
use App\Settings\Product;
use Carbon\CarbonInterface;
use Database\Factories\LeadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $contact_id
 * @property int $product_id
 * @property int $pipeline_id
 * @property int $current_stage_id
 * @property string|null $source
 * @property string|null $business_problem
 * @property string|null $current_solution
 * @property int|null $branch_count
 * @property string|null $budget_note
 * @property DecisionMakerStatus $decision_maker_status
 * @property string|null $purchase_timeline_note
 * @property string|null $next_action
 * @property CarbonInterface|null $next_follow_up_on
 * @property CarbonInterface|null $marked_on
 * @property string|null $marked_label
 * @property string|null $lost_reason
 * @property CarbonInterface|null $closed_at
 * @property bool $automation_paused
 * @property bool $taken_over
 * @property bool $needs_personal_attention
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Contact $contact
 * @property-read Product $product
 * @property-read Pipeline $pipeline
 * @property-read PipelineStage $currentStage
 */
#[Fillable([
    'contact_id',
    'product_id',
    'pipeline_id',
    'current_stage_id',
    'source',
    'business_problem',
    'current_solution',
    'branch_count',
    'budget_note',
    'decision_maker_status',
    'purchase_timeline_note',
    'next_action',
    'next_follow_up_on',
    'marked_on',
    'marked_label',
    'lost_reason',
    'closed_at',
    'automation_paused',
    'taken_over',
    'needs_personal_attention',
])]
#[UseFactory(LeadFactory::class)]
class Lead extends Model
{
    /** @use HasFactory<LeadFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'branch_count' => 'integer',
            'decision_maker_status' => DecisionMakerStatus::class,
            'next_follow_up_on' => 'date',
            'marked_on' => 'date',
            'closed_at' => 'datetime',
            'automation_paused' => 'boolean',
            'taken_over' => 'boolean',
            'needs_personal_attention' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<Pipeline, $this>
     */
    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }

    /**
     * @return BelongsTo<PipelineStage, $this>
     */
    public function currentStage(): BelongsTo
    {
        return $this->belongsTo(PipelineStage::class, 'current_stage_id');
    }

    /**
     * @return HasMany<LeadStageHistory, $this>
     */
    public function stageHistory(): HasMany
    {
        return $this->hasMany(LeadStageHistory::class)->orderBy('id');
    }

    /**
     * @return HasMany<LeadNote, $this>
     */
    public function notes(): HasMany
    {
        return $this->hasMany(LeadNote::class)->orderByDesc('id');
    }

    /**
     * @return HasOne<Conversation, $this>
     */
    public function conversation(): HasOne
    {
        return $this->hasOne(Conversation::class);
    }

    /**
     * @return HasMany<ActivityLog, $this>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    /**
     * @param  Builder<Lead>  $query
     * @return Builder<Lead>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('closed_at');
    }

    public function outcome(): string
    {
        return match ($this->currentStage->kind) {
            StageKind::Won => 'won',
            StageKind::Lost => 'lost',
            StageKind::ArchivedNurture => 'nurture',
            StageKind::Active => 'open',
        };
    }
}
