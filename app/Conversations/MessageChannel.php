<?php

namespace App\Conversations;

enum MessageChannel: string
{
    case Desk = 'desk';
    case WhatsApp = 'whatsapp';
}
