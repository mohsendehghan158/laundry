<?php

declare(strict_types=1);

namespace App\Modules\Orders\Enums;

enum DiscountType: string
{
    /** A fixed number of rials off the order. */
    case Amount = 'amount';

    /** A percentage of the order's subtotal. */
    case Percent = 'percent';
}
