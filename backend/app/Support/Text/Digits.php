<?php

declare(strict_types=1);

namespace App\Support\Text;

final class Digits
{
    private const array PERSIAN = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];

    private const array ARABIC = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];

    private const array LATIN = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

    /**
     * Converts Persian and Arabic-Indic digits to Latin digits so values
     * typed on a Persian keyboard validate and compare consistently.
     */
    public static function toLatin(string $value): string
    {
        return str_replace(self::ARABIC, self::LATIN, str_replace(self::PERSIAN, self::LATIN, $value));
    }
}
