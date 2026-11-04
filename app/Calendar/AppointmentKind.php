<?php

namespace App\Calendar;

enum AppointmentKind: string
{
    case Call = 'call';
    case Meeting = 'meeting';
    case Demo = 'demo';
    case SiteVisit = 'site_visit';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Call => 'Call',
            self::Meeting => 'Meeting',
            self::Demo => 'Demo',
            self::SiteVisit => 'Site visit',
            self::Other => 'Other',
        };
    }
}
