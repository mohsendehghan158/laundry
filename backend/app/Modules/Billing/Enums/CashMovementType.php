<?php

declare(strict_types=1);

namespace App\Modules\Billing\Enums;

enum CashMovementType: string
{
    /** Cash put into the drawer (e.g. change from the bank). */
    case In = 'in';

    /** Cash taken out (e.g. buying supplies). */
    case Out = 'out';
}
