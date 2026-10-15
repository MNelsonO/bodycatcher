<?php

namespace App\Security;

enum Role: string
{
    case Owner = 'owner';
    case Sales = 'sales';
}
