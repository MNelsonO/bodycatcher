<?php

namespace App\WhatsApp;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $dedupe_key
 * @property array<string, mixed> $payload
 * @property CarbonInterface|null $processed_at
 * @property string|null $processing_error
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
#[Fillable(['dedupe_key', 'payload', 'processed_at', 'processing_error'])]
class WebhookEvent extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }
}
