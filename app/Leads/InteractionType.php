<?php

namespace App\Leads;

enum InteractionType: string
{
    case PhoneCall = 'phone_call';
    case Meeting = 'meeting';
    case Note = 'note';
    case Visit = 'visit';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::PhoneCall => 'Phone call',
            self::Meeting => 'Meeting',
            self::Note => 'Note',
            self::Visit => 'Visit',
            self::Other => 'Other',
        };
    }
}
