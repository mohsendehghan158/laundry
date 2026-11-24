<?php

declare(strict_types=1);

use App\Modules\Orders\Services\DueDateCalculator;
use Carbon\CarbonImmutable;

// Tehran is UTC+3:30. 2026-11-24 is a Tuesday; Friday (5) is closed.
function due(string $receivedTehran, int $hours, array $closed = [5], string $opening = '09:00', string $closing = '21:00'): string
{
    $received = CarbonImmutable::parse($receivedTehran, 'Asia/Tehran');

    return (new DueDateCalculator)
        ->dueAt($received, $hours, 'Asia/Tehran', $opening, $closing, $closed)
        ->setTimezone('Asia/Tehran')
        ->format('D Y-m-d H:i');
}

it('adds hours within the same working day', function () {
    expect(due('2026-11-24 10:00', 8))->toBe('Tue 2026-11-24 18:00');
});

it('carries the rest over to the next opening', function () {
    // 10:00 → 21:00 is 11 hours; 13 more from Wednesday 09:00.
    expect(due('2026-11-24 10:00', 24))->toBe('Thu 2026-11-26 10:00');
});

it('skips closed weekdays', function () {
    // Thursday 20:00 + 3h: one hour on Thursday, Friday closed, two on Saturday.
    expect(due('2026-11-26 20:00', 3))->toBe('Sat 2026-11-28 11:00');
});

it('starts counting at opening for orders taken before hours or on a closed day', function () {
    expect(due('2026-11-24 07:30', 2))->toBe('Tue 2026-11-24 11:00')
        ->and(due('2026-11-27 12:00', 2))->toBe('Sat 2026-11-28 11:00');
});

it('treats closing at or before opening as open around the clock', function () {
    expect(due('2026-11-24 22:00', 5, [], '00:00', '00:00'))->toBe('Wed 2026-11-25 03:00');
});

it('falls back to plain hours when every day is marked closed', function () {
    expect(due('2026-11-24 10:00', 30, [0, 1, 2, 3, 4, 5, 6]))->toBe('Wed 2026-11-25 16:00');
});
