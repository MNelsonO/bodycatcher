<?php

use App\Billing\TaxLedgerEntry;
use App\Expenses\Expense;
use App\Models\User;

it('creates an expense and logs tax when present', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/expenses', [
            'title' => 'Fuel for pickups',
            'category' => 'transport',
            'vendor' => 'Shell',
            'amount' => 2000,
            'tax_amount' => 320,
            'currency' => 'KES',
            'spent_on' => '2026-10-15',
            'system_name' => 'expenses',
            'notes' => 'Westlands route',
        ])
        ->assertRedirect();

    expect(Expense::query()->count())->toBe(1)
        ->and(TaxLedgerEntry::query()->where('source', 'expense')->count())->toBe(1);
});

it('shows expense stats for the filtered month', function () {
    $user = User::factory()->create();
    Expense::query()->create([
        'created_by_user_id' => $user->id,
        'title' => 'Ads',
        'category' => 'ads',
        'amount' => 5000,
        'tax_amount' => 0,
        'currency' => 'KES',
        'spent_on' => now()->toDateString(),
        'system_name' => 'expenses',
    ]);

    $this->actingAs($user)
        ->get('/expenses?month='.now()->format('Y-m'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('expenses/index')
            ->where('stats.total_rows', 1)
        );
});
