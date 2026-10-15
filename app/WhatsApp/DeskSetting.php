<?php

namespace App\WhatsApp;

use App\Settings\Product;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property bool $automatic_sends_paused
 * @property int|null $inbound_product_id
 * @property bool $live_assistant_enabled
 * @property string|null $messaging_driver
 * @property-read Product|null $inboundProduct
 */
#[Fillable(['automatic_sends_paused', 'inbound_product_id', 'live_assistant_enabled', 'messaging_driver'])]
class DeskSetting extends Model
{
    protected $table = 'desk_settings';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'automatic_sends_paused' => 'boolean',
            'live_assistant_enabled' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function inboundProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'inbound_product_id');
    }

    public static function current(): self
    {
        $setting = self::query()->first();

        if ($setting !== null) {
            return $setting;
        }

        return self::query()->create([
            'automatic_sends_paused' => false,
            'live_assistant_enabled' => false,
        ]);
    }
}
