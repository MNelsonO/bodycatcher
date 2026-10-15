<?php

namespace App\Conversations;

enum MessageDirection: string
{
    case Inbound = 'inbound';
    case Outbound = 'outbound';
}
