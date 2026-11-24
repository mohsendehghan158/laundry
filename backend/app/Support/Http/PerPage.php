<?php

declare(strict_types=1);

namespace App\Support\Http;

use Illuminate\Http\Request;

final class PerPage
{
    public const int DEFAULT = 25;

    public const int MAX = 100;

    /**
     * Reads the "per_page" query parameter, clamped to a safe range.
     */
    public static function from(Request $request): int
    {
        return max(1, min(self::MAX, $request->integer('per_page', self::DEFAULT)));
    }
}
