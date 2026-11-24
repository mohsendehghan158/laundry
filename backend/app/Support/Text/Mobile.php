<?php

declare(strict_types=1);

namespace App\Support\Text;

final class Mobile
{
    /**
     * Brings an Iranian mobile number to the stored form "09121234567".
     * Accepts Persian digits, spaces and dashes, and the forms
     * "9121234567", "+989121234567" and "00989121234567". Anything that
     * does not look like a mobile number is returned cleaned but otherwise
     * unchanged, so validation can reject it.
     */
    public static function normalize(string $value): string
    {
        $digits = preg_replace('/[\s\-()]/', '', Digits::toLatin(trim($value))) ?? '';

        return match (true) {
            (bool) preg_match('/^(?:\+|00)98(9\d{9})$/', $digits, $m) => '0'.$m[1],
            (bool) preg_match('/^98(9\d{9})$/', $digits, $m) => '0'.$m[1],
            (bool) preg_match('/^(9\d{9})$/', $digits, $m) => '0'.$m[1],
            default => $digits,
        };
    }

    /**
     * The digits worth searching for in a partial number: "0912 12" → "091212",
     * "+98912" → "0912".
     */
    public static function searchable(string $value): string
    {
        $digits = preg_replace('/\D/', '', Digits::toLatin($value)) ?? '';

        return preg_replace('/^(?:0098|98)(?=9)/', '0', $digits) ?? $digits;
    }
}
