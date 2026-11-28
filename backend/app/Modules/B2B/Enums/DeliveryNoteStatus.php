<?php

declare(strict_types=1);

namespace App\Modules\B2B\Enums;

enum DeliveryNoteStatus: string
{
    case Draft = 'draft';

    /** Counted and signed by both sides; no longer changes. */
    case Confirmed = 'confirmed';
}
