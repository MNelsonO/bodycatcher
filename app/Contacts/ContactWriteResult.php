<?php

namespace App\Contacts;

class ContactWriteResult
{
    public function __construct(
        public Contact $contact,
        public bool $matchedExisting,
    ) {}
}
