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
}
