<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Enums;

/**
 * How an item's quantity is counted and priced.
 */
enum PriceUnit: string
{
    /** Counted one by one; each unit becomes tagged pieces. */
    case Piece = 'piece';

    /** Weighed; quantity in kilograms (washing by weight). */
    case Kilogram = 'kg';

    /** Measured; quantity in square metres (carpets, curtains). */
    case SquareMetre = 'sqm';

    /** Quantities of this unit may have a fractional part. */
    public function isMeasured(): bool
    {
        return $this !== self::Piece;
    }
}
