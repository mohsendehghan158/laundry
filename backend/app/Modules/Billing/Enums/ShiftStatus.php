<?php

declare(strict_types=1);

namespace App\Modules\Billing\Enums;

enum ShiftStatus: string
{
    case Open = 'open';

    case Closed = 'closed';
}
