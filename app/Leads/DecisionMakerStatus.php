<?php

namespace App\Leads;

enum DecisionMakerStatus: string
{
    case Unknown = 'unknown';
    case Yes = 'yes';
    case No = 'no';
}
