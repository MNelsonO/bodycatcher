<?php

namespace App\Calendar;

use App\FollowUps\FollowUpTask;
use App\FollowUps\FollowUpTaskStatus;
use App\Leads\Lead;
use App\Settings\DisplayClock;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class CalendarData
{
    public function __construct(private DisplayClock $clock) {}

    /**
     * The visible grid for a month: whole weeks, Monday first, in desk time.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: CarbonImmutable}
     */
    public function range(?string $month): array
    {
        $timezone = $this->clock->timezone();
        $anchor = $month !== null && preg_match('/^\d{4}-\d{2}$/', $month) === 1
            ? CarbonImmutable::createFromFormat('Y-m-d H:i:s', $month.'-01 00:00:00', $timezone)
            : CarbonImmutable::now($timezone)->startOfMonth();

        if ($anchor === null) {
            $anchor = CarbonImmutable::now($timezone)->startOfMonth();
        }

        $start = $anchor->startOfMonth()->startOfWeek(CarbonInterface::MONDAY);
        $end = $anchor->endOfMonth()->endOfWeek(CarbonInterface::SUNDAY);

        return [$anchor, $start, $end];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function events(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $events = [];
        $startUtc = $start->utc();
        $endUtc = $end->utc();

        Appointment::query()
            ->with(['contact', 'lead.product'])
            ->whereBetween('starts_at', [$startUtc, $endUtc])
            ->orderBy('starts_at')
            ->get()
            ->each(function (Appointment $appointment) use (&$events): void {
                $events[] = $this->appointment($appointment);
            });

        Lead::query()
            ->with(['contact', 'product'])
            ->whereNull('closed_at')
            ->whereNotNull('next_follow_up_on')
            ->whereBetween('next_follow_up_on', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->each(function (Lead $lead) use (&$events): void {
                $events[] = [
                    'key' => 'follow-up-'.$lead->id,
                    'type' => 'follow_up',
                    'date' => $lead->next_follow_up_on?->toDateString(),
                    'time' => null,
                    'end_time' => null,
                    'title' => $lead->next_action ?: 'Follow up',
                    'subtitle' => $lead->contact->full_name.' · '.$lead->product->name,
                    'status' => 'due',
                    'href' => '/leads/'.$lead->id,
                ];
            });

        FollowUpTask::query()
            ->with(['lead.contact'])
            ->where('status', FollowUpTaskStatus::Pending)
            ->whereBetween('due_at', [$startUtc, $endUtc])
            ->get()
            ->each(function (FollowUpTask $task) use (&$events): void {
                $local = $task->due_at->copy()->timezone($this->clock->timezone());
                $events[] = [
                    'key' => 'task-'.$task->id,
                    'type' => $task->action->value === 'send_whatsapp' ? 'scheduled_message' : 'task',
                    'date' => $local->toDateString(),
                    'time' => $local->format('H:i'),
                    'end_time' => null,
                    'title' => $task->action->value === 'send_whatsapp' ? 'WhatsApp: '.mb_strimwidth($task->body, 0, 60, '…') : mb_strimwidth($task->body, 0, 60, '…'),
                    'subtitle' => $task->lead->contact->full_name,
                    'status' => $task->held_reason !== null ? 'held' : 'pending',
                    'href' => '/leads/'.$task->lead_id,
                ];
            });

        usort($events, fn (array $a, array $b): int => [$a['date'], $a['time'] ?? '00:00'] <=> [$b['date'], $b['time'] ?? '00:00']);

        return $events;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function upcoming(int $limit = 6): array
    {
        return Appointment::query()
            ->with(['contact', 'lead.product'])
            ->where('status', AppointmentStatus::Scheduled)
            ->where('ends_at', '>=', now())
            ->orderBy('starts_at')
            ->limit($limit)
            ->get()
            ->map(fn (Appointment $appointment): array => $this->appointment($appointment))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function appointment(Appointment $appointment): array
    {
        $timezone = $this->clock->timezone();
        $start = $appointment->starts_at->copy()->timezone($timezone);
        $end = $appointment->ends_at->copy()->timezone($timezone);

        return [
            'key' => 'appointment-'.$appointment->id,
            'id' => $appointment->id,
            'type' => 'appointment',
            'kind' => $appointment->kind->value,
            'kind_label' => $appointment->kind->label(),
            'date' => $start->toDateString(),
            'time' => $start->format('H:i'),
            'end_time' => $end->format('H:i'),
            'starts_at_local' => $start->format('Y-m-d\TH:i'),
            'duration_minutes' => (int) max(5, $start->diffInMinutes($end)),
            'day_label' => $start->format('D d M'),
            'title' => $appointment->title,
            'subtitle' => $appointment->contact !== null
                ? $appointment->contact->full_name.($appointment->lead !== null ? ' · '.$appointment->lead->product->name : '')
                : null,
            'location' => $appointment->location,
            'notes' => $appointment->notes,
            'status' => $appointment->status->value,
            'status_label' => $appointment->status->label(),
            'contact_id' => $appointment->contact_id,
            'lead_id' => $appointment->lead_id,
            'href' => $appointment->lead_id !== null ? '/leads/'.$appointment->lead_id : ($appointment->contact_id !== null ? '/contacts/'.$appointment->contact_id : null),
        ];
    }
}
