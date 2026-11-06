<?php

namespace App\Services;

class PhoneNormalizer
{
    /** Keep digits only, preserving a leading international "+". */
    public function normalize(string $phone): string
    {
        $phone = trim($phone);
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        return str_starts_with($phone, '+') ? "+{$digits}" : $digits;
    }
}
