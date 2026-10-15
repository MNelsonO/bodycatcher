<?php

namespace App\Settings;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class DisplayClock
{
    public function timezone(): string
    {
        $timezone = config('app.display_timezone');

        return is_string($timezone) && $timezone !== '' ? $timezone : 'Africa/Nairobi';
    }

    public function now(): CarbonInterface
    {
        return Carbon::now($this->timezone());
    }

    public function today(): string
    {
        return $this->now()->toDateString();
    }

    public function formatDate(?CarbonInterface $date): ?string
    {
        return $date?->format('d M Y');
    }

    public function formatDateTime(CarbonInterface|string|null $utc): ?string
    {
        if ($utc === null || $utc === '') {
            return null;
        }

        $moment = $utc instanceof CarbonInterface ? $utc : Carbon::parse($utc);

        return $moment->timezone($this->timezone())->format('d M Y, H:i');
    }

    /**
     * Value for HTML datetime-local inputs in the display timezone.
     */
    public function formatDateTimeLocal(CarbonInterface|string|null $utc): ?string
    {
        if ($utc === null || $utc === '') {
            return null;
        }

        $moment = $utc instanceof CarbonInterface ? $utc : Carbon::parse($utc);

        return $moment->timezone($this->timezone())->format('Y-m-d\TH:i');
    }

    /**
     * @return array{0: CarbonInterface, 1: CarbonInterface}
     */
    public function todayUtcRange(): array
    {
        $start = $this->now()->startOfDay()->utc();
        $end = $this->now()->endOfDay()->utc();

        return [$start, $end];
    }

    /**
     * Named desk periods in display timezone, returned as UTC bounds.
     *
     * @return array{0: CarbonInterface, 1: CarbonInterface}|null
     */
    public function periodUtcRange(string $period): ?array
    {
        $now = $this->now();

        return match ($period) {
            'today' => [
                $now->copy()->startOfDay()->utc(),
                $now->copy()->endOfDay()->utc(),
            ],
            'this_week' => [
                $now->copy()->startOfWeek()->utc(),
                $now->copy()->endOfWeek()->utc(),
            ],
            'next_week' => [
                $now->copy()->addWeek()->startOfWeek()->utc(),
                $now->copy()->addWeek()->endOfWeek()->utc(),
            ],
            'this_month' => [
                $now->copy()->startOfMonth()->utc(),
                $now->copy()->endOfMonth()->utc(),
            ],
            'last_7_days' => [
                $now->copy()->subDays(6)->startOfDay()->utc(),
                $now->copy()->endOfDay()->utc(),
            ],
            'last_30_days' => [
                $now->copy()->subDays(29)->startOfDay()->utc(),
                $now->copy()->endOfDay()->utc(),
            ],
            default => null,
        };
    }
}
