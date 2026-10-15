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
            // Local Kenyan convenience: 07… / 01… → +254…
            $normalized = '+254'.substr($digits, 1);
        } elseif (strlen($digits) === 9 && preg_match('/^[17]/', $digits) === 1) {
            $normalized = '+254'.$digits;
        } elseif (strlen($digits) >= 8 && strlen($digits) <= 15 && ! str_starts_with($digits, '0')) {
            // Bare international digits without a leading + (e.g. 2567…)
            $normalized = '+'.$digits;
        } else {
            throw new InvalidPhoneNumber('Enter a valid phone number in international (+…) or Kenyan local form.');
        }

        if (preg_match('/^\+[1-9]\d{7,14}$/', $normalized) !== 1) {
            throw new InvalidPhoneNumber('Enter a valid phone number.');
        }

        return $normalized;
    }
}
