<?php

namespace App\Contacts;

enum PreferredChannel: string
{
    case Whatsapp = 'whatsapp';
    case Phone = 'phone';
    case Email = 'email';
}
