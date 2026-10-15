<?php

use App\Models\User;
use App\Security\Role;

it('shows lead counts under each product', function () {
    $lead = whatsappOpenLead();
    $owner = User::factory()->create(['role' => Role::Owner->value]);

    $this->actingAs($owner)
        ->get('/products')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('products/index')
            ->has('products')
            ->where('products.0.leads_total', fn ($value) => is_int($value))
        );

    expect($lead->product_id)->not->toBeNull();
});

it('marks a date on a lead and can push it to the calendar', function () {
    $user = User::factory()->create();
    $lead = whatsappOpenLead();

    $this->actingAs($user)
        ->post("/leads/{$lead->id}/mark-date", [
            'marked_on' => '2026-10-22',
            'marked_label' => 'Site visit',
            'add_to_calendar' => '1',
        ])
        ->assertRedirect();

    $lead->refresh();

    expect($lead->marked_on?->toDateString())->toBe('2026-10-22')
        ->and($lead->marked_label)->toBe('Site visit')
        ->and(\App\Calendar\CalendarActivity::query()->where('lead_id', $lead->id)->exists())->toBeTrue();
});
