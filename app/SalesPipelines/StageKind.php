<?php

namespace App\SalesPipelines;

enum StageKind: string
{
    case Active = 'active';
    case Won = 'won';
    case Lost = 'lost';
    case ArchivedNurture = 'archived-nurture';

    public function isClosed(): bool
    {
        return $this !== self::Active;
    }
}
