<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Enums\ShiftStatus;
use App\Modules\Billing\Models\CashShift;
use App\Modules\Identity\Models\User;
use App\Modules\Organization\Models\Branch;
use App\Support\Exceptions\ApiException;
use Illuminate\Database\UniqueConstraintViolationException;

final class OpenShift
{
    /**
     * Opens the branch's cash register with the cash already in the
     * drawer. A branch has at most one open shift.
     */
    public function handle(User $actor, Branch $branch, int $openingFloat, ?string $notes): CashShift
    {
        if (! $actor->canAccessBranch($branch)) {
            throw ApiException::conflict('order_branch_unavailable');
        }

        if (CashShift::query()->where('branch_id', $branch->id)->where('status', ShiftStatus::Open)->exists()) {
            throw ApiException::conflict('shift_already_open');
        }

        try {
            return CashShift::query()->create([
                'branch_id' => $branch->id,
                'status' => ShiftStatus::Open,
                'opened_by' => $actor->id,
                'opened_at' => now(),
                'opening_float' => $openingFloat,
                'notes' => $notes,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Someone opened it a moment earlier.
            throw ApiException::conflict('shift_already_open');
        }
    }
}
