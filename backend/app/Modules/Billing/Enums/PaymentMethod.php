<?php

declare(strict_types=1);

namespace App\Modules\Billing\Enums;

enum PaymentMethod: string
{
    /** Banknotes in the drawer. */
    case Cash = 'cash';

    /** Card terminal (POS device); the trace number is the reference. */
    case Card = 'card';

    /** Bank transfer or card-to-card; the reference identifies it. */
    case Transfer = 'transfer';
}
