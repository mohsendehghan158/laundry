<?php

declare(strict_types=1);

namespace App\Support\Text;

use Carbon\CarbonInterface;

/**
 * Gregorian → Jalali (Persian calendar) conversion for texts the server
 * writes itself, such as SMS messages. Uses the arithmetic of the
 * widely used jalaali algorithm (valid for Jalali years 1–3177).
 */
final class Jalali
{
    /** Leap-cycle breaks of the Jalali calendar. */
    private const array BREAKS = [-61, 9, 38, 199, 426, 686, 756, 818, 1111, 1181, 1210, 1635, 2060, 2097, 2192, 2262, 2324, 2394, 2456, 3178];

    /**
     * @return array{0: int, 1: int, 2: int} [year, month 1–12, day]
     */
    public static function fromGregorian(int $gy, int $gm, int $gd): array
    {
        $jdn = self::g2d($gy, $gm, $gd);
        $jy = $gy - 621;
        $r = self::cal($jy);
        $jdn1f = self::g2d($gy, 3, $r['march']);
        $k = $jdn - $jdn1f;

        if ($k >= 0) {
            if ($k <= 185) {
                return [$jy, 1 + intdiv($k, 31), ($k % 31) + 1];
            }
            $k -= 186;
        } else {
            $jy--;
            $k += 179;
            if ($r['leap'] === 1) {
                $k++;
            }
        }

        return [$jy, 7 + intdiv($k, 30), ($k % 30) + 1];
    }

    /**
     * "۱۴۰۵/۰۹/۰۳ ۱۴:۳۰" in the given time zone, with Persian digits.
     */
    public static function format(CarbonInterface $moment, string $timezone, bool $withTime = true): string
    {
        $local = $moment->copy()->setTimezone($timezone);
        [$y, $m, $d] = self::fromGregorian($local->year, $local->month, $local->day);
        $text = sprintf('%04d/%02d/%02d', $y, $m, $d).($withTime ? ' '.$local->format('H:i') : '');

        return Digits::toPersian($text);
    }

    /**
     * @return array{leap: int, march: int} leap: years since the last leap year (0 = this is one); march: Gregorian day of March of Farvardin 1
     */
    private static function cal(int $jy): array
    {
        $gy = $jy + 621;
        $leapJ = -14;
        $jp = self::BREAKS[0];
        $jump = 0;

        for ($i = 1, $n = count(self::BREAKS); $i < $n; $i++) {
            $jm = self::BREAKS[$i];
            $jump = $jm - $jp;
            if ($jy < $jm) {
                break;
            }
            $leapJ += intdiv($jump, 33) * 8 + intdiv($jump % 33, 4);
            $jp = $jm;
        }

        $n = $jy - $jp;
        $leapJ += intdiv($n, 33) * 8 + intdiv(($n % 33) + 3, 4);
        if ($jump % 33 === 4 && $jump - $n === 4) {
            $leapJ++;
        }

        $leapG = intdiv($gy, 4) - intdiv((intdiv($gy, 100) + 1) * 3, 4) - 150;
        $march = 20 + $leapJ - $leapG;

        if ($jump - $n < 6) {
            $n = $n - $jump + intdiv($jump + 4, 33) * 33;
        }
        $leap = (((($n + 1) % 33) - 1) % 4);
        if ($leap === -1) {
            $leap = 4;
        }

        return ['leap' => $leap, 'march' => $march];
    }

    /** Julian day number of a Gregorian date. */
    private static function g2d(int $gy, int $gm, int $gd): int
    {
        $d = intdiv(($gy + intdiv($gm - 8, 6) + 100100) * 1461, 4)
            + intdiv(153 * (($gm + 9) % 12) + 2, 5)
            + $gd - 34840408;

        return $d - intdiv(intdiv($gy + 100100 + intdiv($gm - 8, 6), 100) * 3, 4) + 752;
    }
}
