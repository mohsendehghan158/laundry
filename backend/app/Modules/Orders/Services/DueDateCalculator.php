<?php

declare(strict_types=1);

namespace App\Modules\Orders\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * When an order is due: the turnaround duration counted in working hours
 * only. Time outside opening hours and on closed weekdays does not count,
 * so 24 working hours from Thursday evening may well land on Saturday.
 * Public holidays will be added with the holiday calendar.
 */
final class DueDateCalculator
{
    /**
     * @param  string  $opening  "09:00", in the organization's time zone
     * @param  string  $closing  "21:00"; equal to or before opening means open around the clock
     * @param  list<int>  $closedDays  0 = Sunday … 6 = Saturday
     */
    public function dueAt(
        CarbonInterface $receivedAt,
        int $hours,
        string $timezone,
        string $opening,
        string $closing,
        array $closedDays,
    ): CarbonImmutable {
        $remaining = $hours * 60;
        $moment = CarbonImmutable::instance($receivedAt)->setTimezone($timezone)->startOfMinute();

        // Every day of the week closed would never end; fall back to plain hours.
        if (count(array_unique($closedDays)) >= 7) {
            return $moment->addMinutes($remaining)->utc();
        }

        for ($day = 0; $day < 800; $day++) {
            if (in_array($moment->dayOfWeek, $closedDays, true)) {
                $moment = $this->nextDayOpening($moment, $opening, $closing);

                continue;
            }

            [$open, $close] = $this->window($moment, $opening, $closing);

            if ($moment->lessThan($open)) {
                $moment = $open;
            }

            if ($moment->greaterThanOrEqualTo($close)) {
                $moment = $this->nextDayOpening($moment, $opening, $closing);

                continue;
            }

            $available = (int) $moment->diffInMinutes($close);

            if ($remaining <= $available) {
                return $moment->addMinutes($remaining)->utc();
            }

            $remaining -= $available;
            $moment = $this->nextDayOpening($moment, $opening, $closing);
        }

        return $moment->addMinutes($remaining)->utc();
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function window(CarbonImmutable $day, string $opening, string $closing): array
    {
        $open = $day->setTimeFromTimeString($opening);
        $close = $day->setTimeFromTimeString($closing);

        if ($close->lessThanOrEqualTo($open)) {
            return [$day->startOfDay(), $day->startOfDay()->addDay()];
        }

        return [$open, $close];
    }

    private function nextDayOpening(CarbonImmutable $moment, string $opening, string $closing): CarbonImmutable
    {
        return $this->window($moment->addDay()->startOfDay(), $opening, $closing)[0];
    }
}
