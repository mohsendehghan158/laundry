<?php

declare(strict_types=1);

namespace App\Modules\Production\Enums;

enum ClaimKind: string
{
    case Damaged = 'damaged';
    case Lost = 'lost';
}
