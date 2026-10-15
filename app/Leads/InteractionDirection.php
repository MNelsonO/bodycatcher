<?php

namespace App\Leads;

enum InteractionDirection: string
{
    case Inbound = 'inbound';
    case Outbound = 'outbound';
    case None = 'none';
}
