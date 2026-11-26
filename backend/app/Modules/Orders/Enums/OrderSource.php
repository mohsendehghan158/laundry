<?php

declare(strict_types=1);

namespace App\Modules\Orders\Enums;

/** Where an order was taken. */
enum OrderSource: string
{
    /** Brought in to a branch. */
    case Counter = 'counter';
    /** Placed by the customer in the portal, for pickup. */
    case Online = 'online';
}
