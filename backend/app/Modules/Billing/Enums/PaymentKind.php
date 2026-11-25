<?php

declare(strict_types=1);

namespace App\Modules\Billing\Enums;

enum PaymentKind: string
{
    /** Money received. */
    case Payment = 'payment';

    /** Money given back. */
    case Refund = 'refund';
}
