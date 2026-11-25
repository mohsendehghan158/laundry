<?php

declare(strict_types=1);

namespace App\Modules\Production\Enums;

/**
 * A side state on top of a piece's status.
 */
enum PieceFlag: string
{
    /** Failed quality control; goes back through the stations. */
    case Rework = 'rework';

    /** Held on purpose (waiting for the customer's approval, say). */
    case OnHold = 'on_hold';

    /** At an outside vendor (rugs, leather, tailoring). */
    case Outsourced = 'outsourced';

    /** On its way between branches. */
    case InTransit = 'in_transit';

    /** Damaged in the shop; a claim is open. Still handed back. */
    case Damaged = 'damaged';

    /** Cannot be found; a claim is open. */
    case Lost = 'lost';

    /** Flags under which the piece cannot be worked on or assembled. */
    public function blocksWork(): bool
    {
        return in_array($this, [self::OnHold, self::Outsourced, self::InTransit, self::Lost], true);
    }
}
