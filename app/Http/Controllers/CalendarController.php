<?php

namespace App\Http\Controllers;

use App\Calendar\Appointment;
use App\Calendar\AppointmentKind;
use App\Calendar\AppointmentStatus;
use App\Calendar\CalendarData;
use App\Contacts\Contact;
use App\Http\Requests\AppointmentRequest;
use App\Leads\Actions\RecordActivity;
use App\Leads\ActivityEvent;
use App\Leads\Lead;
use App\Models\User;
use App\Settings\DisplayClock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CalendarController extends Controller
{
    public function __construct(
        private DisplayClock $clock,
        private RecordActivity $activity,
    ) {}

    public function index(Request $request, CalendarData $calendar): Response
    {
        $this->authorize('viewAny', Appointment::class);

        [$anchor, $start, $end] = $calendar->range($request->string('month')->toString() ?: null);

        return Inertia::render('calendar/index', [
            'month' => $anchor->format('Y-m'),
            'month_label' => $anchor->format('F Y'),
            'previous_month' => $anchor->subMonth()->format('Y-m'),
            'next_month' => $anchor->addMonth()->format('Y-m'),
            'grid_start' => $start->toDateString(),
            'grid_end' => $end->toDateString(),
            'today' => $this->clock->today(),
            'events' => $calendar->events($start, $end),
            'kinds' => collect(AppointmentKind::cases())->map(fn (AppointmentKind $kind): array => [
                'value' => $kind->value,
                'label' => $kind->label(),
            ])->all(),
            'statuses' => collect(AppointmentStatus::cases())->map(fn (AppointmentStatus $status): array => [
                'value' => $status->value,
                'label' => $status->label(),
            ])->all(),
            'leads' => Lead::query()
                ->with(['contact:id,full_name', 'product:id,name'])
                ->whereNull('closed_at')
                ->orderByDesc('updated_at')
                ->limit(300)
                ->get()
                ->map(fn (Lead $lead): array => [
                    'id' => $lead->id,
                    'contact_id' => $lead->contact_id,
                    'label' => $lead->contact->full_name.' · '.$lead->product->name,
                ])->all(),
            'contacts' => Contact::query()
                ->whereNull('merged_into_contact_id')
                ->orderBy('full_name')
                ->get(['id', 'full_name', 'primary_phone']),
        ]);
    }

    public function store(AppointmentRequest $request): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $appointment = new Appointment;
        $appointment->status = AppointmentStatus::Scheduled;
        $appointment->created_by_user_id = $actor->id;
        $this->fill($appointment, $request);
        $appointment->save();

        $this->log($appointment, ActivityEvent::AppointmentBooked, $actor, ['title' => $appointment->title]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Booked for '.$this->clock->formatDateTime($appointment->starts_at).'.',
        ]);

        return back();
    }

    public function update(AppointmentRequest $request, Appointment $appointment): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $before = $appointment->starts_at;
        $this->fill($appointment, $request);
        $appointment->save();

        if (! $before->equalTo($appointment->starts_at)) {
            $this->log($appointment, ActivityEvent::AppointmentChanged, $actor, [
                'label' => 'Appointment moved to '.$this->clock->formatDateTime($appointment->starts_at).': '.$appointment->title,
            ]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Appointment saved.']);

        return back();
    }

    public function status(Request $request, Appointment $appointment): RedirectResponse
    {
        $this->authorize('update', $appointment);

        $data = $request->validate([
            'status' => ['required', Rule::enum(AppointmentStatus::class)],
        ]);

        /** @var User $actor */
        $actor = $request->user();
        $appointment->status = AppointmentStatus::from($data['status']);
        $appointment->save();

        $this->log($appointment, ActivityEvent::AppointmentChanged, $actor, [
            'label' => 'Appointment '.mb_strtolower($appointment->status->label()).': '.$appointment->title,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Marked '.mb_strtolower($appointment->status->label()).'.']);

        return back();
    }

    public function destroy(Request $request, Appointment $appointment): RedirectResponse
    {
        $this->authorize('delete', $appointment);

        $appointment->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Appointment removed.']);

        return back();
    }

    private function fill(Appointment $appointment, AppointmentRequest $request): void
    {
        $start = Carbon::parse($request->string('starts_at')->toString(), $this->clock->timezone())->utc();
        $leadId = $request->integer('lead_id') ?: null;
        $contactId = $request->integer('contact_id') ?: null;

        if ($leadId !== null) {
            $contactId = Lead::query()->whereKey($leadId)->value('contact_id') ?? $contactId;
        }

        $appointment->fill([
            'title' => trim($request->string('title')->toString()),
            'kind' => AppointmentKind::from($request->string('kind')->toString()),
            'starts_at' => $start,
            'ends_at' => $start->copy()->addMinutes($request->integer('duration_minutes')),
            'location' => $request->string('location')->trim()->toString() ?: null,
            'notes' => $request->string('notes')->trim()->toString() ?: null,
            'contact_id' => $contactId,
            'lead_id' => $leadId,
        ]);
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function log(Appointment $appointment, ActivityEvent $event, User $actor, array $properties): void
    {
        if ($appointment->lead_id === null && $appointment->contact_id === null) {
            return;
        }

        $appointment->loadMissing(['lead', 'contact']);

        $this->activity->handle($event, $appointment->lead, $appointment->contact, $actor, [
            ...$properties,
            'appointment_id' => $appointment->id,
        ]);
    }
}
