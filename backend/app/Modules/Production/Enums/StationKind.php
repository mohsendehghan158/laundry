<?php

declare(strict_types=1);

namespace App\Modules\Production\Enums;

enum StationKind: string
{
    /** Scanning records that the piece reached this step. */
    case Process = 'process';

    /** Quality control: every scan passes the piece or sends it back for rework. */
    case Qc = 'qc';
}
