<?php

use App\Integrations\IntegrationAccount;
use App\Mail\IntegrationHelpRequestMail;
use App\Models\User;
use App\Security\Role;
use App\WhatsApp\DeskSetting;
use Illuminate\Support\Facades\Mail;

it('shows catalog integrations and can sync a placeholder Meta report', function () {
    $owner = User::factory()->create(['role' => Role::Owner->value]);

    $this->actingAs($owner)
        ->get('/integrations')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('integrations/index')
            ->has('accounts', 5)
            ->has('catalog', 5)
            ->where('can_manage', true)
        );

    $account = IntegrationAccount::query()->where('provider', 'meta_business')->firstOrFail();

    $this->actingAs($owner)
        ->post("/integrations/{$account->id}/sync")
        ->assertRedirect();

    expect($account->fresh()?->last_synced_at)->not->toBeNull();
});

it('saves FlareSend credentials on the desk and switches messaging driver', function () {
    $owner = User::factory()->create(['role' => Role::Owner->value]);

    $this->actingAs($owner)->get('/integrations')->assertOk();

    $account = IntegrationAccount::query()->where('provider', 'flaresend')->firstOrFail();

    $this->actingAs($owner)
        ->put("/integrations/{$account->id}", [
            'label' => 'FlareSend WhatsApp',
            'is_active' => true,
            'base_url' => 'https://api.flaresend.com',
            'messaging_driver' => 'flaresend',
            'credentials' => [
                'api_key' => 'fs-test-key',
                'webhook_secret' => 'fs-webhook-secret',
            ],
        ])
        ->assertRedirect(route('integrations.index'));

    $account->refresh();

    expect($account->credentials['api_key'] ?? null)->toBe('fs-test-key')
        ->and($account->credentials['webhook_secret'] ?? null)->toBe('fs-webhook-secret')
        ->and(DeskSetting::current()->messaging_driver)->toBe('flaresend');

    app(\App\Integrations\Services\IntegrationConfigResolver::class)->applyToConfig();

    expect(config('whatsapp.flaresend.api_key'))->toBe('fs-test-key')
        ->and(config('whatsapp.driver'))->toBe('flaresend');
});

it('forwards integration help to a technical supervisor', function () {
    Mail::fake();

    $owner = User::factory()->create([
        'role' => Role::Owner->value,
        'email' => 'owner@kemet.test',
    ]);
    $sales = User::factory()->create(['role' => Role::Sales->value]);

    $this->actingAs($sales)->get('/integrations')->assertOk();

    $account = IntegrationAccount::query()->where('provider', 'flaresend')->firstOrFail();

    $this->actingAs($sales)
        ->post("/integrations/{$account->id}/help", [
            'note' => 'Need help wiring the webhook and a demo reply.',
            'stuck_on' => 'Webhook URL',
        ])
        ->assertRedirect(route('integrations.index'));

    Mail::assertSent(IntegrationHelpRequestMail::class, function (IntegrationHelpRequestMail $mail) use ($owner) {
        return $mail->hasTo($owner->email)
            && $mail->integrationTitle === 'FlareSend WhatsApp'
            && str_contains($mail->note, 'webhook');
    });

    $this->assertDatabaseHas('calendar_activities', [
        'user_id' => $owner->id,
        'activity_type' => 'integration_help',
    ]);
});
