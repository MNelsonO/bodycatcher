<?php

namespace Database\Factories;

use App\SalesPipelines\Pipeline;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pipeline>
 */
class PipelineFactory extends Factory
{
    protected $model = Pipeline::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Pipeline '.fake()->unique()->word(),
            'description' => null,
            'is_default' => false,
            'archived_at' => null,
        ];
    }
}
