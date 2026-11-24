<?php

declare(strict_types=1);

namespace App\Modules\Orders\Services;

use App\Modules\Catalog\Enums\UpchargeType;
use App\Modules\Orders\Enums\DiscountType;
use InvalidArgumentException;

/**
 * Turns priced lines into an order's amounts. A pure function: the same
 * input always gives the same output, and it is used both for the
 * counter's live preview and when the order is saved.
 *
 * All amounts are whole rials. Quantities are passed in thousandths
 * (2.5 kg = 2500) and percentages in hundredths of a percent (9.5% = 950),
 * so no floating point is involved. Every rounding is half up.
 *
 * Per line:
 *   base      = unit price × quantity
 *   upcharges = fixed amount × quantity, or percent of base
 *   surcharge = turnaround percent of (base + upcharges)
 *   total     = base + upcharges + surcharge
 * Order:
 *   discount  = fixed rials or percent of the subtotal, never more than it
 *   tax       = VAT on (subtotal − discount), added on top or, when
 *               prices include VAT, the part of it that is tax
 */
final class PriceCalculator
{
    /**
     * @param  list<array{unit_price: int, quantity_milli: int, upcharges: list<array{type: UpchargeType, value: int}>}>  $lines
     * @param  array{type: DiscountType, value_bp: int}|null  $discount  value in rials (amount) or basis points (percent)
     * @return array{lines: list<array{base: int, upcharge: int, upcharges: list<int>, surcharge: int, total: int}>, subtotal: int, discount: int, tax: int, total: int}
     */
    public function calculate(array $lines, int $surchargePercent, ?array $discount, int $vatBasisPoints, bool $pricesIncludeVat): array
    {
        $quotes = [];
        $subtotal = 0;

        foreach ($lines as $line) {
            if ($line['quantity_milli'] <= 0 || $line['unit_price'] < 0) {
                throw new InvalidArgumentException('Quantities must be positive and prices non-negative.');
            }

            $base = self::share($line['unit_price'] * $line['quantity_milli'], 1000);
            $upcharges = array_map(
                fn (array $upcharge): int => match ($upcharge['type']) {
                    UpchargeType::Fixed => self::share($upcharge['value'] * $line['quantity_milli'], 1000),
                    UpchargeType::Percent => self::share($base * $upcharge['value'], 100),
                },
                $line['upcharges'],
            );
            $upchargeTotal = array_sum($upcharges);
            $surcharge = self::share(($base + $upchargeTotal) * $surchargePercent, 100);
            $total = $base + $upchargeTotal + $surcharge;

            $quotes[] = ['base' => $base, 'upcharge' => $upchargeTotal, 'upcharges' => $upcharges, 'surcharge' => $surcharge, 'total' => $total];
            $subtotal += $total;
        }

        $discountAmount = match ($discount['type'] ?? null) {
            DiscountType::Amount => $discount['value_bp'],
            DiscountType::Percent => self::share($subtotal * $discount['value_bp'], 10_000),
            default => 0,
        };
        $discountAmount = max(0, min($discountAmount, $subtotal));
        $taxable = $subtotal - $discountAmount;

        if ($pricesIncludeVat) {
            $tax = $taxable - self::share($taxable * 10_000, 10_000 + $vatBasisPoints);
            $total = $taxable;
        } else {
            $tax = self::share($taxable * $vatBasisPoints, 10_000);
            $total = $taxable + $tax;
        }

        return ['lines' => $quotes, 'subtotal' => $subtotal, 'discount' => $discountAmount, 'tax' => $tax, 'total' => $total];
    }

    /** $numerator / $denominator rounded half up, for non-negative numbers. */
    private static function share(int $numerator, int $denominator): int
    {
        return intdiv($numerator * 2 + $denominator, $denominator * 2);
    }

    /** "2.5" → 2500. */
    public static function milli(string|int|float $quantity): int
    {
        return (int) round((float) $quantity * 1000);
    }

    /** 9.5 → 950. */
    public static function basisPoints(string|int|float $percent): int
    {
        return (int) round((float) $percent * 100);
    }
}
