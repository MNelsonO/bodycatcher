<?php

use App\Expenses\Expense;
use App\Models\User;
use App\Settings\Product;

it('shows the reports page with product and spend metrics for a date range', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create([
        'name' => 'Laundry OS Report',
        'slug' => 'laundry-os-report',
    ]);

    $spentOn = now()->toDateString();

    Expense::query()->create([
        'created_by_user_id' => $user->id,
        'title' => 'Ads boost',
        'category' => 'ads',
        'amount' => 2500,
        'tax_amount' => 0,
        'currency' => 'KES',
        'spent_on' => $spentOn,
        'system_name' => 'expenses',
    ]);

    expect(Expense::query()->count())->toBe(1)
        ->and((float) Expense::query()->sum('amount'))->toBe(2500.0);

    $from = now()->subDays(7)->toDateString();
    $to = now()->toDateString();

    $this->actingAs($user)
        ->get("/reports?from={$from}&to={$to}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('reports/index')
            ->where('report.filters.from', $from)
            ->where('report.filters.to', $to)
            ->has('report.products')
            ->has('report.daily')
            ->has('report.summary.leads')
            ->where('report.summary.spend', 2500)
            ->where('report.products.0.product_name', $product->name)
        );
});
