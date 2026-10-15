<?php

namespace App\FollowUps;

enum FollowUpTaskStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Done = 'done';
    case Cancelled = 'cancelled';
    case Refused = 'refused';
    case Failed = 'failed';
}
