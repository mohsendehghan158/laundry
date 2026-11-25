<?php

declare(strict_types=1);

namespace App\Modules\Production\Support;

use App\Modules\Identity\Models\User;
use App\Modules\Orders\Models\Piece;
use App\Modules\Production\Enums\PieceEvent;
use App\Modules\Production\Models\PieceScan;

final class PieceHistory
{
    /** Appends an event to the piece's history, at the piece's current branch unless told otherwise. */
    public static function record(Piece $piece, PieceEvent $event, ?User $user, ?int $stationId = null, ?int $branchId = null, ?string $note = null): PieceScan
    {
        return PieceScan::query()->create([
            'organization_id' => $piece->organization_id,
            'piece_id' => $piece->id,
            'order_id' => $piece->order_id,
            'event' => $event,
            'station_id' => $stationId,
            'branch_id' => $branchId ?? $piece->branch_id,
            'user_id' => $user?->id,
            'note' => $note,
        ]);
    }
}
