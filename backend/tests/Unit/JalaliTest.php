<?php

declare(strict_types=1);

use App\Support\Text\Jalali;
use Carbon\CarbonImmutable;

it('converts Gregorian dates to Jalali', function (array $gregorian, array $jalali) {
    expect(Jalali::fromGregorian(...$gregorian))->toBe($jalali);
})->with([
    'Nowruz 1405' => [[2026, 3, 21], [1405, 1, 1]],
    'today in the tests' => [[2026, 11, 24], [1405, 9, 3]],
    'last day of a 31-day month' => [[2026, 9, 22], [1405, 6, 31]],
    'first of Mehr' => [[2026, 9, 23], [1405, 7, 1]],
    'Esfand 30 of leap 1403' => [[2025, 3, 20], [1403, 12, 30]],
    'Nowruz 1404' => [[2025, 3, 21], [1404, 1, 1]],
    'Yalda 1402' => [[2023, 12, 21], [1402, 9, 30]],
    'long ago' => [[1979, 2, 11], [1357, 11, 22]],
    'a gregorian leap day' => [[2024, 2, 29], [1402, 12, 10]],
]);

it('formats a moment in a time zone with Persian digits', function () {
    // 21:00 UTC on Nov 23 is 00:30 on Nov 24 in Tehran.
    $moment = CarbonImmutable::parse('2026-11-23 21:00:00', 'UTC');

    expect(Jalali::format($moment, 'Asia/Tehran'))->toBe('۱۴۰۵/۰۹/۰۳ ۰۰:۳۰')
        ->and(Jalali::format($moment, 'UTC', false))->toBe('۱۴۰۵/۰۹/۰۲');
});
