<?php

namespace App\Contacts;

/**
 * Markets where Kemet Tech runs ads. Used to classify clients by country
 * so the desk and live AI know which region's currency, language, and
 * conversation area apply.
 */
class CountryCatalog
{
    /**
     * @return list<array{code: string, name: string}>
     */
    public function all(): array
    {
        return [
            ['code' => 'KE', 'name' => 'Kenya'],
            ['code' => 'UG', 'name' => 'Uganda'],
            ['code' => 'TZ', 'name' => 'Tanzania'],
            ['code' => 'RW', 'name' => 'Rwanda'],
            ['code' => 'ET', 'name' => 'Ethiopia'],
            ['code' => 'NG', 'name' => 'Nigeria'],
            ['code' => 'GH', 'name' => 'Ghana'],
            ['code' => 'ZA', 'name' => 'South Africa'],
            ['code' => 'ZM', 'name' => 'Zambia'],
            ['code' => 'ZW', 'name' => 'Zimbabwe'],
            ['code' => 'MW', 'name' => 'Malawi'],
            ['code' => 'SS', 'name' => 'South Sudan'],
            ['code' => 'SO', 'name' => 'Somalia'],
            ['code' => 'CD', 'name' => 'DR Congo'],
            ['code' => 'AE', 'name' => 'United Arab Emirates'],
            ['code' => 'SA', 'name' => 'Saudi Arabia'],
            ['code' => 'GB', 'name' => 'United Kingdom'],
            ['code' => 'US', 'name' => 'United States'],
            ['code' => 'CA', 'name' => 'Canada'],
            ['code' => 'IN', 'name' => 'India'],
            ['code' => 'XX', 'name' => 'Other'],
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function options(): array
    {
        return collect($this->all())
            ->map(fn (array $country): array => [
                'value' => $country['code'],
                'label' => $country['name'],
            ])
            ->all();
    }

    public function nameFor(?string $code): ?string
    {
        if ($code === null || trim($code) === '') {
            return null;
        }

        $needle = strtoupper(trim($code));

        foreach ($this->all() as $country) {
            if ($country['code'] === $needle) {
                return $country['name'];
            }
        }

        return null;
    }

    public function isValid(?string $code): bool
    {
        return $this->nameFor($code) !== null;
    }
}
