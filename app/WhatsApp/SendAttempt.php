<?php

namespace App\WhatsApp;

enum SendAttempt: string
{
    case Accepted = 'accepted';
    case Transient = 'transient';
    case Unknown = 'unknown';
    case Failed = 'failed';
}
