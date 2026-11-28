<?php

declare(strict_types=1);

namespace App\Modules\Billing\Enums;

enum BuyerType: string
{
    /** A person: national code of 10 digits. */
    case Individual = 'individual';

    /** A company or institution: national id of 11 digits. */
    case Legal = 'legal';
}
