<?php

declare(strict_types=1);

namespace App\Modules\Billing\Enums;

enum InvoiceStatus: string
{
    case Issued = 'issued';

    /** Cancelled with a reason; the number stays taken. */
    case Void = 'void';
}
