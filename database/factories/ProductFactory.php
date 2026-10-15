<?php

namespace Database\Factories;

use App\Settings\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'name' => is_string($name) ? Str::title($name) : 'Sample Product',
            'slug' => fake()->unique()->slug(),
            'description' => 'Operator-maintained product for the Kemet Tech desk.',
            'pricing_notes' => null,
            'is_active' => true,
            'archived_at' => null,
        ];
    }
}
