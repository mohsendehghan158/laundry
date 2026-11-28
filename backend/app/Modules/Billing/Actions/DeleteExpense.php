<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Enums\CashMovementType;
use App\Modules\Billing\Enums\ShiftStatus;
use App\Modules\Billing\Models\CashShift;
use App\Modules\Billing\Models\Expense;
use App\Modules\Identity\Models\User;
use App\Support\Exceptions\ApiException;
use Illuminate\Support\Facades\DB;

final class DeleteExpense
{
    public function __construct(private readonly RecordCashMovement $recordCashMovement) {}

    /**
     * Removes an expense. One paid from the register puts its cash back
     * into the same shift, which must still be open.
     */
    public function handle(User $actor, Expense $expense): void
    {
        DB::transaction(function () use ($actor, $expense): void {
            if ($expense->cashMovement !== null) {
                $shift = CashShift::query()->findOrFail($expense->cashMovement->cash_shift_id);

                if ($shift->status !== ShiftStatus::Open) {
                    throw ApiException::conflict('expense_shift_closed');
                }

                $this->recordCashMovement->handle($actor, $shift, CashMovementType::In, $expense->amount, __('billing.expense_reversed', ['description' => $expense->description]));
            }

            $expense->delete();
            activity('expense')->causedBy($actor)->performedOn($expense)->event('deleted')->withProperties(['amount' => $expense->amount])->log('deleted');
        });
    }
}
