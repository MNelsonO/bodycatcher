<?php

use App\Integrations\IntegrationAccount;
use App\Models\User;
use App\Security\Role;

it('shows integration accounts and can sync a placeholder Meta report', function () {
    $owner = User::factory()->create(['role' => Role::Owner->value]);

    $this->actingAs($owner)
        ->get('/integrations')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('integrations/index')
            ->has('accounts', 2)
        );

    $account = IntegrationAccount::query()->where('provider', 'meta_business')->firstOrFail();

    $this->actingAs($owner)
        ->post("/integrations/{$account->id}/sync")
        ->assertRedirect();

    expect($account->fresh()?->last_synced_at)->not->toBeNull();
});
