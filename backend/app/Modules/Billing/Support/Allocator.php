<?php

declare(strict_types=1);

namespace App\Modules\Billing\Support;

/**
 * Splits a whole amount over weighted parts so the parts add up exactly
 * (largest remainder method): an order-level discount or tax spread over
 * its lines, say.
 */
final class Allocator
{
    /**
     * @param  list<int>  $weights  non-negative
     * @return list<int>
     */
    public static function split(int $amount, array $weights): array
    {
        if ($weights === []) {
            return [];
        }

        $total = array_sum($weights);
        if ($total <= 0) {
            $weights = array_fill(0, count($weights), 1);
            $total = count($weights);
        }

        $parts = [];
        $remainders = [];
        foreach ($weights as $i => $weight) {
            $exact = $amount * $weight;
            $parts[$i] = intdiv($exact, $total);
            $remainders[$i] = $exact % $total;
        }

        // Hand out what rounding down left over, biggest remainders first (earlier lines win ties).
        $left = $amount - array_sum($parts);
        arsort($remainders, SORT_NUMERIC);
        foreach (array_keys($remainders) as $i) {
            if ($left <= 0) {
                break;
            }
            $parts[$i]++;
            $left--;
        }

        ksort($parts);

        return array_values($parts);
    }
}
