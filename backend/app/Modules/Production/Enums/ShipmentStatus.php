<?php

declare(strict_types=1);

namespace App\Modules\Production\Enums;

enum ShipmentStatus: string
{
    /** At the vendor; some pieces may already be back. */
    case Sent = 'sent';

    /** Every piece is back. */
    case Closed = 'closed';
}
