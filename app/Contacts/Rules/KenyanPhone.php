<?php

namespace App\Contacts\Rules;

use App\Contacts\InvalidPhoneNumber;
use App\Contacts\PhoneNormalizer;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class KenyanPhone implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('Enter a valid phone number.');

            return;
        }

        try {
            app(PhoneNormalizer::class)->normalize($value);
        } catch (InvalidPhoneNumber $exception) {
            $fail($exception->getMessage());
        }
    }
}
