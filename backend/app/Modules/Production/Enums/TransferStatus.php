<?php

declare(strict_types=1);

namespace App\Modules\Production\Enums;

enum TransferStatus: string
{
    /** Being filled at the sending branch. */
    case Open = 'open';

    /** On its way. */
    case Sent = 'sent';

    /** Checked in at the receiving branch (missing pieces stay in transit). */
    case Received = 'received';

    case Cancelled = 'cancelled';
}
