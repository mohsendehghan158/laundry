<?php

declare(strict_types=1);

namespace App\Modules\Production\Concerns;

use App\Modules\Identity\Models\User;
use App\Modules\Orders\Models\Piece;
use App\Modules\Production\Enums\TransferStatus;
use App\Modules\Production\Models\Transfer;

trait ReachesBranches
{
    /**
     * Ids of the branches the user works in, or null for all of them.
     *
     * @return list<int>|null
     */
    protected function branchIds(User $actor): ?array
    {
        return $actor->hasAllBranchAccess() ? null : array_map('intval', $actor->branches()->pluck('branches.id')->all());
    }

    protected function inBranches(User $actor, int ...$branchIds): bool
    {
        $mine = $this->branchIds($actor);

        return $mine === null || array_intersect($branchIds, $mine) !== [];
    }

    /**
     * Pieces of the user's branches' orders, now in one of the user's
     * branches, or on their way to one.
     */
    protected function reachesPiece(User $actor, Piece $piece): bool
    {
        if ($this->inBranches($actor, $piece->order->branch_id, ...($piece->branch_id === null ? [] : [$piece->branch_id]))) {
            return true;
        }

        return Transfer::query()
            ->whereIn('to_branch_id', $this->branchIds($actor) ?? [])
            ->whereIn('status', [TransferStatus::Sent, TransferStatus::Received])
            ->whereHas('pieces', fn ($query) => $query->whereKey($piece->id)->whereNull('transfer_pieces.received_at'))
            ->exists();
    }
}
