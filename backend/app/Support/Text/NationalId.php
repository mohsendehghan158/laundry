<?php

declare(strict_types=1);

namespace App\Support\Text;

/**
 * Check digits of Iranian identity numbers: the 10-digit national code
 * of a person (کد ملی) and the 11-digit national id of a legal entity
 * (شناسه ملی).
 */
final class NationalId
{
    public static function isValidPersonCode(string $code): bool
    {
        if (preg_match('/^\d{10}$/', $code) !== 1 || count(array_unique(str_split($code))) === 1) {
            return false;
        }

        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += (int) $code[$i] * (10 - $i);
        }

        $remainder = $sum % 11;
        $check = (int) $code[9];

        return $remainder < 2 ? $check === $remainder : $check === 11 - $remainder;
    }

    public static function isValidLegalId(string $id): bool
    {
        if (preg_match('/^\d{11}$/', $id) !== 1) {
            return false;
        }

        $offset = (int) $id[9] + 2;
        $weights = [29, 27, 23, 19, 17];
        $sum = 0;
        for ($i = 0; $i < 10; $i++) {
            $sum += ((int) $id[$i] + $offset) * $weights[$i % 5];
        }

        $remainder = $sum % 11;

        return ($remainder === 10 ? 0 : $remainder) === (int) $id[10];
    }
}
