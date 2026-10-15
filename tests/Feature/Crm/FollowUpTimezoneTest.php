<?php

use App\Leads\Lead;
use App\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

it('counts follow-ups against the Africa/Nairobi day, not the UTC day', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-14 22:30:00', 'UTC'));

    $dueToday = Lead::factory()->create([
        'next_follow_up_on' => '2026-10-15',
        'next_action' => 'Call the Westlands shop',
        'created_at' => '2026-10-14 22:30:00',
        'updated_at' => '2026-10-14 22:30:00',
    ]);

    $overdue = Lead::factory()->create([
        'next_follow_up_on' => '2026-10-14',
        'next_action' => 'Send the Thika questions',
        'created_at' => '2026-10-14 18:00:00',
        'updated_at' => '2026-10-14 18:00:00',
    ]);

    Lead::factory()->create([
        'next_follow_up_on' => '2026-10-16',
        'created_at' => '2026-10-14 18:00:00',
        'updated_at' => '2026-10-14 18:00:00',
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('metrics.follow_ups_due_today', 1)
            ->where('metrics.overdue_follow_ups', 1)
            ->where('metrics.new_leads_today', 1)
            ->where('metrics.today', '2026-10-15')
            ->where('metrics.timezone', 'Africa/Nairobi')
            ->where('metrics.follow_up_rows.0.id', $overdue->id)
            ->where('metrics.follow_up_rows.0.reason', 'Overdue')
            ->where('metrics.follow_up_rows.0.next_action', 'Send the Thika questions')
            ->where('metrics.follow_up_rows.1.id', $dueToday->id)
            ->where('metrics.follow_up_rows.1.reason', 'Due today')
            ->where('metrics.follow_up_rows.1.next_action', 'Call the Westlands shop')
        );

    Carbon::setTestNow();
});
