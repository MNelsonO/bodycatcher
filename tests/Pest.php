<?php

use App\Models\User;
use App\SalesPipelines\Pipeline;
use App\SalesPipelines\PipelineStage;
use App\SalesPipelines\PipelineStageTransition;
use App\SalesPipelines\StageKind;
use App\Settings\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function crmSalesUser(): User
{
    return User::factory()->create();
}

/**
 * @return array{pipeline: Pipeline, stage: PipelineStage, won: PipelineStage, lost: PipelineStage, side: PipelineStage, product: Product, other: Product}
 */
function crmDesk(): array
{
    $pipeline = Pipeline::factory()->create(['name' => 'Kemet Tech sales', 'is_default' => true]);
    $stage = PipelineStage::factory()->create([
        'pipeline_id' => $pipeline->id,
        'name' => 'New Lead',
        'kind' => StageKind::Active,
        'position' => 1,
    ]);
    $won = PipelineStage::factory()->create([
        'pipeline_id' => $pipeline->id,
        'name' => 'Won',
        'kind' => StageKind::Won,
        'position' => 2,
    ]);
    $lost = PipelineStage::factory()->create([
        'pipeline_id' => $pipeline->id,
        'name' => 'Lost',
        'kind' => StageKind::Lost,
        'position' => 3,
    ]);
    $side = PipelineStage::factory()->create([
        'pipeline_id' => $pipeline->id,
        'name' => 'Side door',
        'kind' => StageKind::Active,
        'position' => 4,
    ]);

    foreach ([$won, $lost] as $target) {
        PipelineStageTransition::query()->create([
            'pipeline_id' => $pipeline->id,
            'from_stage_id' => $stage->id,
            'to_stage_id' => $target->id,
        ]);
    }

    return [
        'pipeline' => $pipeline,
        'stage' => $stage,
        'won' => $won,
        'lost' => $lost,
        'side' => $side,
        'product' => Product::factory()->create(['name' => 'Kemet Laundry OS', 'slug' => 'kemet-laundry-os']),
        'other' => Product::factory()->create(['name' => 'Kemet Route Desk', 'slug' => 'kemet-route-desk']),
    ];
}
