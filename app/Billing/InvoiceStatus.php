<?php

namespace App\Billing;

enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Unpaid = 'unpaid';
    case Paid = 'paid';
    case Overdue = 'overdue';
    case Void = 'void';

    public function isOpen(): bool
    {
        return in_array($this, [self::Draft, self::Sent, self::Unpaid, self::Overdue], true);
    }
}
