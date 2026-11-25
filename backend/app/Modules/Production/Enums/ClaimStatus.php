<?php

declare(strict_types=1);

namespace App\Modules\Production\Enums;

enum ClaimStatus: string
{
    case Open = 'open';

    /** Settled with the customer (compensation, repair…). */
    case Resolved = 'resolved';

    /** Not upheld: not the shop's doing, or the lost piece was found. */
    case Rejected = 'rejected';
}
