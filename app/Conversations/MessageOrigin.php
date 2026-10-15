<?php

namespace App\Conversations;

enum MessageOrigin: string
{
    case Manual = 'manual';
    case Automatic = 'automatic';
    case Inbound = 'inbound';
}
