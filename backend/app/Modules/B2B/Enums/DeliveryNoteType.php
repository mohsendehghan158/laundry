<?php

declare(strict_types=1);

namespace App\Modules\B2B\Enums;

enum DeliveryNoteType: string
{
    /** Items the company sent in; confirmed, it becomes an order. */
    case In = 'in';

    /** Items sent back for an order; confirmed, the order is handed over. */
    case Out = 'out';
}
