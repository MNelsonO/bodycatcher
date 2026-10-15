<?php

namespace App\Conversations;

enum MessageStatus: string
{
    case Logged = 'logged';
    case Queued = 'queued';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Read = 'read';
    case Failed = 'failed';
    case Unknown = 'unknown';
    case Refused = 'refused';
    case Received = 'received';
}
