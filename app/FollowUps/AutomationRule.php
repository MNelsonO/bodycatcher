<?php

namespace App\FollowUps;

use App\Settings\Product;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property FollowUpTrigger $trigger
 * @property FollowUpAction $action
 * @property int|null $delay_hours
 * @property string|null $local_time
 * @property string $body
 * @property int|null $product_id
 * @property bool $is_active
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Product|null $product
 */
#[Fillable([
    'name',
    'trigger',
    'action',
    'delay_hours',
    'local_time',
    'body',
    'product_id',
    'is_active',
])]
class AutomationRule extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'trigger' => FollowUpTrigger::class,
            'action' => FollowUpAction::class,
            'delay_hours' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return HasMany<FollowUpTask, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(FollowUpTask::class);
    }
}
