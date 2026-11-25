<?php

declare(strict_types=1);

namespace App\Modules\Production\Enums;

/**
 * What happened to a piece, one row each in its history.
 */
enum PieceEvent: string
{
    case Scanned = 'scanned';
    case QcPassed = 'qc_passed';
    case QcFailed = 'qc_failed';
    case Held = 'held';
    case Released = 'released';
    case Assembled = 'assembled';
    case Racked = 'racked';
    case SentBack = 'sent_back';
    case TransferredOut = 'transferred_out';
    case TransferredIn = 'transferred_in';
    case Outsourced = 'outsourced';
    case Returned = 'returned';
    case Damaged = 'damaged';
    case Lost = 'lost';
    case Found = 'found';
    case HandedOver = 'handed_over';
}
