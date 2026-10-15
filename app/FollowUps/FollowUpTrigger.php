<?php

namespace App\FollowUps;

enum FollowUpTrigger: string
{
    case NoResponse = 'no_response';
    case FollowUpDue = 'follow_up_due';
    case StageDelay = 'stage_delay';
}
