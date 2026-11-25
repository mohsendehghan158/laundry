<?php

declare(strict_types=1);

namespace App\Modules\Production\Support;

use App\Support\Text\Digits;

final class Tags
{
    /** A scanned or typed code as stored: Latin digits, capitals, no spaces. */
    public static function normalize(string $code): string
    {
        return mb_strtoupper((string) preg_replace('/\s+/u', '', Digits::toLatin($code)));
    }
}
