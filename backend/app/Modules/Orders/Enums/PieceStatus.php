<?php

declare(strict_types=1);

namespace App\Modules\Orders\Enums;

/**
 * Where a piece is in its life: tagged at intake, worked on at stations,
 * gathered with the rest of its order (assembly), on the rack, and back
 * with the customer. Side states (rework, outsourced…) are its flag.
 */
enum PieceStatus: string
{
    case Received = 'received';
    case InProcess = 'in_process';
    case Assembled = 'assembled';
    case Ready = 'ready';
    case HandedOver = 'handed_over';

    /** Still in the workshop: not yet gathered for the customer. */
    public function inWork(): bool
    {
        return in_array($this, [self::Received, self::InProcess], true);
    }
}
