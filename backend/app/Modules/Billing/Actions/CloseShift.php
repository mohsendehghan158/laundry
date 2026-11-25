<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Enums\ShiftStatus;
use App\Modules\Billing\Models\CashShift;
use App\Modules\Identity\Models\User;
use App\Support\Exceptions\ApiException;
use Illuminate\Support\Facades\DB;

final class CloseShift
{
    /**
     * Closes the register with the cash counted in the drawer and records
     * the difference from what should be there (negative = short).
     */
    public function handle(User $actor, CashShift $shift, int $countedCash, ?string $notes): CashShift
    {
        return DB::transaction(function () use ($actor, $shift, $countedCash, $notes): CashShift {
            $shift = CashShift::query()->lockForUpdate()->findOrFail($shift->id);

            if ($shift->status !== ShiftStatus::Open) {
                throw ApiException::conflict('shift_not_open');
            }

            $expected = $shift->totals()['expected_cash'];

            $shift->update([
                'status' => ShiftStatus::Closed,
                'closed_by' => $actor->id,
                'closed_at' => now(),
                'expected_cash' => $expected,
                'counted_cash' => $countedCash,
                'difference' => $countedCash - $expected,
                'notes' => $notes ?? $shift->notes,
            ]);

            activity('cash')->causedBy($actor)->performedOn($shift)->event('shift_closed')
                ->withProperties(['expected' => $expected, 'counted' => $countedCash, 'difference' => $countedCash - $expected])
                ->log('shift_closed');

            return $shift;
        });
    }
}
