<?php

namespace App\Integrations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IntegrationAccount extends Model
{
    protected $fillable = [
        'provider',
        'label',
        'is_active',
        'settings',
        'credentials',
        'last_synced_at',
        'last_sync_error',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'settings' => 'array',
            'credentials' => 'encrypted:array',
            'last_synced_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<IntegrationReport, $this>
     */
    public function reports(): HasMany
    {
        return $this->hasMany(IntegrationReport::class)->orderByDesc('period_end');
    }
}
