<?php

namespace App\Http\Controllers;

use App\Calendar\CalendarActivity;
use App\Http\Requests\CalendarActivityRequest;
use App\Integrations\Services\GoogleCalendarSync;
use App\Settings\DisplayClock;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CalendarController extends Controller
{
    public function index(Request $request, DisplayClock $clock, GoogleCalendarSync $google): Response
    {
        $month = $request->string('month')->toString();
        $day = $request->string('day')->toString();
        $anchor = $month !== ''
            ? CarbonImmutable::createFromFormat('Y-m', $month, $clock->timezone())->startOfMonth()
            : CarbonImmutable::now($clock->timezone())->startOfMonth();

        $gridStart = $anchor->startOfWeek(CarbonImmutable::MONDAY);
        $gridEnd = $anchor->endOfMonth()->endOfWeek(CarbonImmutable::SUNDAY);

        $activities = CalendarActivity::query()
            ->where('user_id', $request->user()->id)
            ->whereBetween('activity_on', [$gridStart->toDateString(), $gridEnd->toDateString()])
            ->when($day !== '', fn ($query) => $query->whereDate('activity_on', $day))
            ->orderBy('activity_on')
            ->orderBy('id')
            ->get()
            ->map(fn (CalendarActivity $activity): array => [
                'id' => $activity->id,
                'activity_on' => $activity->activity_on?->toDateString(),
                'title' => $activity->title,
                'notes' => $activity->notes,
                'activity_type' => $activity->activity_type,
                'color' => $activity->color,
                'lead_id' => $activity->lead_id,
                'external_calendar_id' => $activity->external_calendar_id,
            ]);

        return Inertia::render('calendar/index', [
            'month' => $anchor->format('Y-m'),
            'month_label' => $anchor->format('F Y'),
            'selected_day' => $day !== '' ? $day : null,
            'timezone' => $clock->timezone(),
            'days' => $this->days($gridStart, $gridEnd, $anchor, $activities->all()),
            'activities' => $activities,
            'prev_month' => $anchor->subMonth()->format('Y-m'),
            'next_month' => $anchor->addMonth()->format('Y-m'),
            'google_calendar' => $google->status(),
        ]);
    }

    public function store(CalendarActivityRequest $request, GoogleCalendarSync $google): RedirectResponse
    {
        $activity = CalendarActivity::query()->create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        $message = 'Calendar activity saved.';

        if ($google->activated()) {
            $push = $google->push($activity);
            $message = $push['ok']
                ? 'Calendar activity saved and pushed to Google Calendar.'
                : 'Calendar activity saved locally. '.$push['message'];
        }

        Inertia::flash('toast', [
            'type' => $google->activated() && ! ($push['ok'] ?? true) ? 'warning' : 'success',
            'message' => $message,
        ]);

        return redirect()->route('calendar.index', [
            'month' => substr($request->string('activity_on')->toString(), 0, 7),
            'day' => $request->string('activity_on')->toString(),
        ]);
    }

    public function update(CalendarActivityRequest $request, CalendarActivity $activity): RedirectResponse
    {
        abort_unless($activity->user_id === $request->user()->id, 403);

        $activity->fill($request->validated());
        $activity->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Calendar activity updated.']);

        return redirect()->route('calendar.index', [
            'month' => substr($request->string('activity_on')->toString(), 0, 7),
            'day' => $request->string('activity_on')->toString(),
        ]);
    }

    public function destroy(Request $request, CalendarActivity $activity): RedirectResponse
    {
        abort_unless($activity->user_id === $request->user()->id, 403);
        $month = $activity->activity_on?->format('Y-m');
        $activity->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Calendar activity removed.']);

        return redirect()->route('calendar.index', ['month' => $month]);
    }

    /**
     * @param  list<array<string, mixed>>  $activities
     * @return list<array{date: string, in_month: bool, count: int}>
     */
    private function days(
        CarbonImmutable $start,
        CarbonImmutable $end,
        CarbonImmutable $anchor,
        array $activities,
    ): array {
        $counts = [];

        foreach ($activities as $activity) {
            $date = (string) $activity['activity_on'];
            $counts[$date] = ($counts[$date] ?? 0) + 1;
        }

        $days = [];
        $cursor = $start;

        while ($cursor <= $end) {
            $date = $cursor->toDateString();
            $days[] = [
                'date' => $date,
                'in_month' => $cursor->month === $anchor->month && $cursor->year === $anchor->year,
                'count' => $counts[$date] ?? 0,
            ];
            $cursor = $cursor->addDay();
        }

        return $days;
    }
}
