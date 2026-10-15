<?php

namespace App\WhatsApp;

class OptOutKeywords
{
    public static function matches(string $body): bool
    {
        $normalized = mb_strtolower(trim($body));

        if ($normalized === '') {
            return false;
        }

        $keywords = config('whatsapp.opt_out_keywords');

        if (! is_array($keywords)) {
            return false;
        }

        foreach ($keywords as $keyword) {
            if (is_string($keyword) && $keyword !== '' && $normalized === mb_strtolower($keyword)) {
                return true;
            }
        }

        return false;
    }
}
