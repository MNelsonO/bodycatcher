<?php

namespace App\Contacts;

enum ConsentStatus: string
{
    case Unknown = 'unknown';
    case OptedIn = 'opted_in';
    case OptedOut = 'opted_out';
}
