<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Enums;

enum UpchargeType: string
{
    /** A fixed amount in rials per unit. */
    case Fixed = 'fixed';

    /** A whole percent of the line's base price. */
    case Percent = 'percent';
}
