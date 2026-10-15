<?php

use App\Models\User;
use App\Money\MoneyAccount;
use App\Money\MoneyDebt;
use App\Money\MoneyRecurring;

it('tracks payment accounts, debts, and recurring monthly items', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/money/accounts', [
            'name' => 'Equity current',
            'institution' => 'Equity Bank',
            'account_type' => 'bank',
            'account_number' => '012345',
            'currency' => 'KES',
            'balance' => 120000,
            'is_active' => true,
        ])
        ->assertRedirect('/money');

    expect(MoneyAccount::query()->count())->toBe(1);

    $account = MoneyAccount::query()->firstOrFail();

    $this->actingAs($user)
        ->post('/money/debts', [
            'direction' => 'payable',
            'party_name' => 'Landlord',
            'title' => 'Shop deposit balance',
            'amount' => 40000,
            'currency' => 'KES',
            'due_on' => now()->addDays(10)->toDateString(),
            'money_account_id' => $account->id,
        ])
        ->assertRedirect('/money');

    $this->actingAs($user)
        ->post('/money/debts', [
            'direction' => 'receivable',
            'party_name' => 'Amina Laundry',
            'title' => 'October retainer',
            'amount' => 25000,
            'currency' => 'KES',
            'due_on' => now()->addDays(5)->toDateString(),
        ])
        ->assertRedirect('/money');

    $this->actingAs($user)
        ->post('/money/recurrings', [
            'direction' => 'outflow',
            'title' => 'Office rent',
            'party_name' => 'Landlord',
            'amount' => 35000,
            'currency' => 'KES',
            'cadence' => 'monthly',
            'day_of_month' => 1,
            'next_due_on' => now()->toDateString(),
            'money_account_id' => $account->id,
        ])
        ->assertRedirect('/money');

    $this->actingAs($user)
        ->get('/money')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('money/index')
            ->where('summary.cash', 120000)
            ->where('summary.i_owe', 40000)
            ->where('summary.owed_to_me', 25000)
            ->has('accounts', 1)
            ->has('debts', 2)
            ->has('recurrings', 1)
        );

    $debt = MoneyDebt::query()->where('direction', 'payable')->firstOrFail();
    $this->actingAs($user)
        ->post("/money/debts/{$debt->id}/settle")
        ->assertRedirect('/money');

    expect($debt->fresh()?->status)->toBe('settled');

    $recurring = MoneyRecurring::query()->firstOrFail();
    $before = $recurring->next_due_on?->toDateString();
    $this->actingAs($user)
        ->post("/money/recurrings/{$recurring->id}/paid")
        ->assertRedirect('/money');

    expect($recurring->fresh()?->next_due_on?->toDateString())->not->toBe($before);
});
