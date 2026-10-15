<?php

namespace App\FollowUps;

enum FollowUpAction: string
{
    case SendWhatsApp = 'send_whatsapp';
    case OperatorTask = 'operator_task';
}
