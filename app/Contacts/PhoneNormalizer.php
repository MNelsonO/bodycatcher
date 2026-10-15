<?php

namespace App\Contacts;

class PhoneNormalizer
{
    public function normalize(string $input): string
    {
        $trimmed = trim($input);

        if ($trimmed === '') {
            throw new InvalidPhoneNumber('A phone number is required.');
        }

        $hasPlus = str_starts_with($trimmed, '+');
        $digits = preg_replace('/\D+/', '', $trimmed);

        if (! is_string($digits) || $digits === '') {
            throw new InvalidPhoneNumber('Enter a valid phone number.');
        }

        if ($hasPlus) {
            $normalized = '+'.$digits;
        } elseif (str_starts_with($digits, '254') && strlen($digits) >= 12) {
            $normalized = '+'.$digits;
        } elseif (str_starts_with($digits, '0') && strlen($digits) === 10) {
            $normalized = '+254'.substr($digits, 1);
        } elseif (strlen($digits) === 9) {
            $normalized = '+254'.$digits;
        } else {
            throw new InvalidPhoneNumber('Enter a valid phone number. Local Kenyan numbers start with 07 or 01.');
        }

        if (preg_match('/^\+[1-9]\d{7,14}$/', $normalized) !== 1) {
            throw new InvalidPhoneNumber('Enter a valid phone number.');
        }

        return $normalized;
    }
}
