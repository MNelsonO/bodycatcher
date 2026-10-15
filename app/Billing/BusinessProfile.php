<?php

namespace App\Billing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class BusinessProfile extends Model
{
    protected $fillable = [
        'legal_name',
        'trading_name',
        'address_line1',
        'address_line2',
        'city',
        'country',
        'phone',
        'email',
        'tax_id',
        'logo_path',
        'bank_details',
        'default_terms',
        'default_tax_rate',
        'currency',
    ];

    protected function casts(): array
    {
        return [
            'default_tax_rate' => 'decimal:2',
        ];
    }

    public static function current(): self
    {
        $profile = self::query()->first();

        if ($profile !== null) {
            return $profile;
        }

        return self::query()->create([
            'legal_name' => 'Kemet Tech',
            'country' => 'Kenya',
            'default_tax_rate' => 16,
            'currency' => 'KES',
        ]);
    }

    public function logoUrl(): ?string
    {
        if ($this->logo_path === null || $this->logo_path === '') {
            return null;
        }

        return Storage::disk('public')->url($this->logo_path);
    }

    /**
     * @return list<string>
     */
    public function addressLines(): array
    {
        return array_values(array_filter([
            $this->address_line1,
            $this->address_line2,
            collect([$this->city, $this->country])->filter()->implode(', '),
        ], fn (?string $line): bool => filled($line)));
    }
}
