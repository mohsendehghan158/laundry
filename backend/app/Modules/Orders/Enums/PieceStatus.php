<?php

declare(strict_types=1);

namespace App\Modules\Orders\Enums;

/**
 * Where a piece is in the simple counter flow. Workshop stations
 * (phase 3) add their own scan history on top of this.
 */
enum PieceStatus: string
{
    case Received = 'received';
    case Ready = 'ready';
    case HandedOver = 'handed_over';
}
