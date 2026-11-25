<?php

declare(strict_types=1);

namespace App\Modules\Production\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Orders\Enums\PieceStatus;
use App\Modules\Orders\Models\Piece;
use App\Modules\Production\Enums\PieceEvent;
use App\Modules\Production\Enums\PieceFlag;
use App\Modules\Production\Support\PieceHistory;
use App\Support\Exceptions\ApiException;
use Illuminate\Support\Facades\DB;

final class HoldPiece
{
    /**
     * Holds a piece (waiting for the customer's approval, say): it cannot
     * be scanned or gathered until released.
     */
    public function hold(User $actor, Piece $piece, string $reason): Piece
    {
        if (in_array($piece->status, [PieceStatus::Ready, PieceStatus::HandedOver], true) || $piece->flag !== null && $piece->flag !== PieceFlag::Rework && $piece->flag !== PieceFlag::Damaged) {
            throw ApiException::conflict('piece_cannot_hold');
        }

        return DB::transaction(function () use ($actor, $piece, $reason): Piece {
            $piece->update(['flag' => PieceFlag::OnHold, 'flag_note' => $reason]);
            PieceHistory::record($piece, PieceEvent::Held, $actor, note: $reason);

            return $piece;
        });
    }

    public function release(User $actor, Piece $piece): Piece
    {
        if ($piece->flag !== PieceFlag::OnHold) {
            throw ApiException::conflict('piece_not_held');
        }

        return DB::transaction(function () use ($actor, $piece): Piece {
            $piece->update(['flag' => null, 'flag_note' => null]);
            PieceHistory::record($piece, PieceEvent::Released, $actor);

            return $piece;
        });
    }
}
