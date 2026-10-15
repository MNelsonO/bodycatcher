<?php

namespace App\Ai;

enum DraftStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
}
