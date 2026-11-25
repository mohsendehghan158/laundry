<?php

declare(strict_types=1);

namespace App\Modules\Production\Support;

use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Piece;
use App\Modules\Production\Enums\PieceFlag;
use App\Modules\Production\Enums\TransferStatus;
use Illuminate\Support\Facades\DB;

final class Movable
{
    /**
     * Why a piece cannot leave a branch now (on a transfer or to a
     * vendor), or null when it can: it must be in work at that branch,
     * not held, away or lost, and not already on an open transfer.
     */
    public static function blocker(Piece $piece, int $branchId): ?string
    {
        $piece->loadMissing(['order', 'branch']);

        if (! in_array($piece->order->status, [OrderStatus::Received, OrderStatus::InProcess, OrderStatus::PartiallyReady], true)) {
            return __('production.move.order_closed');
        }
        if (! $piece->status->inWork()) {
            return __('production.move.done');
        }
        if ($piece->branch_id !== $branchId) {
            return __('production.move.elsewhere', ['branch' => $piece->branch->name ?? '—']);
        }
        if ($piece->flag !== null && $piece->flag !== PieceFlag::Damaged) {
            return __('production.move.flagged', ['flag' => __("production.flags.{$piece->flag->value}")]);
        }

        $open = DB::table('transfer_pieces')
            ->join('transfers', 'transfers.id', '=', 'transfer_pieces.transfer_id')
            ->where('transfer_pieces.piece_id', $piece->id)
            ->where('transfers.status', TransferStatus::Open->value)
            ->value('transfers.number');

        return $open === null ? null : __('production.move.in_transfer', ['number' => $open]);
    }

    /** The flag a piece returns to once back: damaged while a damage claim is open, else none. */
    public static function restingFlag(Piece $piece): ?PieceFlag
    {
        return $piece->claims()->where('kind', 'damaged')->where('status', 'open')->exists() ? PieceFlag::Damaged : null;
    }
}
