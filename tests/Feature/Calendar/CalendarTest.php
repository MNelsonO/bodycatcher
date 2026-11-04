<?php

use App\Calendar\Appointment;
use App\Calendar\AppointmentStatus;
use App\Leads\ActivityLog;
use Inertia\Testing\AssertableInertia as Assert;

it('books an appointment for a lead in desk time and shows it on the calendar', function () {
    $lead = whatsappOpenLead();

    $this->post(route('calendar.store'), [
        'title' => 'Demo at the shop',
        'kind' => 'demo',
        'starts_at' => '2026-11-20T10:00',
        'duration_minutes' => 60,
        'location' => 'Westlands',
        'lead_id' => $lead->id,
    ])->assertRedirect();

    $appointment = Appointment::query()->firstOrFail();

    expect($appointment->starts_at->toDateTimeString())->toBe('2026-11-20 07:00:00')
        ->and($appointment->ends_at->toDateTimeString())->toBe('2026-11-20 08:00:00')
        ->and($appointment->contact_id)->toBe($lead->contact_id)
        ->and($appointment->status)->toBe(AppointmentStatus::Scheduled)
        ->and(ActivityLog::query()->where('event', 'appointment_booked')->count())->toBe(1);

    $this->get(route('calendar.index', ['month' => '2026-11']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('calendar/index')
            ->where('month_label', 'November 2026')
            ->where('events', fn ($events) => collect($events)->contains(
                fn ($event) => $event['type'] === 'appointment' && $event['date'] === '2026-11-20' && $event['time'] === '10:00',
            )));

    $this->get(route('leads.show', $lead))
        ->assertInertia(fn (Assert $page) => $page->has('lead.appointments', 1));
});

it('changes the status of an appointment and removes it', function () {
    $lead = whatsappOpenLead();
    $this->post(route('calendar.store'), [
        'title' => 'Call',
        'kind' => 'call',
        'starts_at' => '2026-11-21T09:00',
        'duration_minutes' => 15,
        'lead_id' => $lead->id,
    ]);
    $appointment = Appointment::query()->firstOrFail();

    $this->post(route('calendar.status', $appointment), ['status' => 'completed'])->assertRedirect();
    expect($appointment->refresh()->status)->toBe(AppointmentStatus::Completed);

    $this->delete(route('calendar.destroy', $appointment))->assertRedirect();
    expect(Appointment::query()->count())->toBe(0);
});

it('validates appointments', function () {
    whatsappOpenLead();

    $this->post(route('calendar.store'), ['title' => '', 'kind' => 'party', 'duration_minutes' => 1])
        ->assertSessionHasErrors(['title', 'kind', 'starts_at', 'duration_minutes']);
});

it('shows lead follow-up dates on the calendar', function () {
    $lead = whatsappOpenLead();
    $lead->forceFill(['next_follow_up_on' => '2026-11-25', 'next_action' => 'Send quote'])->save();

    $this->get(route('calendar.index', ['month' => '2026-11']))
        ->assertInertia(fn (Assert $page) => $page->where('events', fn ($events) => collect($events)->contains(
            fn ($event) => $event['type'] === 'follow_up' && $event['title'] === 'Send quote',
        )));
});
