<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Enums\CashMovementType;
use App\Modules\Billing\Enums\ShiftStatus;
use App\Modules\Billing\Models\CashMovement;
use App\Modules\Billing\Models\CashShift;
use App\Modules\Identity\Models\User;
use App\Support\Exceptions\ApiException;
use Illuminate\Support\Facades\DB;

final class RecordCashMovement
{
    public function handle(User $actor, CashShift $shift, CashMovementType $type, int $amount, string $reason): CashMovement
    {
        return DB::transaction(function () use ($actor, $shift, $type, $amount, $reason): CashMovement {
            $shift = CashShift::query()->lockForUpdate()->findOrFail($shift->id);

            if ($shift->status !== ShiftStatus::Open) {
                throw ApiException::conflict('shift_not_open');
            }

            if ($type === CashMovementType::Out && $amount > $shift->totals()['expected_cash']) {
                throw ApiException::conflict('shift_cash_insufficient');
            }

            return $shift->movements()->create([
                'type' => $type,
                'amount' => $amount,
                'reason' => $reason,
                'user_id' => $actor->id,
            ]);
        });
    }
}
