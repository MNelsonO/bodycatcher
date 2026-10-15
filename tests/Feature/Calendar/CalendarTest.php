<?php

use App\Calendar\CalendarActivity;
use App\Models\User;

it('lets an authenticated user open the laundry calendar', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/calendar')
        ->assertOk();
});

it('stores a blue laundry activity on a chosen day', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/calendar', [
            'activity_on' => '2026-10-20',
            'title' => 'Westlands pickup',
            'notes' => 'Blue bag route',
            'activity_type' => 'delivery',
            'color' => 'blue',
        ])
        ->assertRedirect();

    expect(CalendarActivity::query()->where('user_id', $user->id)->first())
        ->title->toBe('Westlands pickup')
        ->color->toBe('blue')
        ->activity_type->toBe('delivery');
});
