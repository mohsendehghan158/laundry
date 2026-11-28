<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Enums\CashMovementType;
use App\Modules\Billing\Enums\PaymentMethod;
use App\Modules\Billing\Enums\ShiftStatus;
use App\Modules\Billing\Models\CashShift;
use App\Modules\Billing\Models\Expense;
use App\Modules\Identity\Models\User;
use App\Support\Exceptions\ApiException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

final class SaveExpense
{
    public function __construct(private readonly RecordCashMovement $recordCashMovement) {}

    /**
     * Records an expense, or changes one. Paid from the register, the cash
     * also leaves the branch's open shift; such an expense keeps its
     * branch, amount and method afterwards.
     *
     * @param  array{branch_id?: int, expense_category_id?: int, spent_on?: string, amount?: int, method?: string, description?: string, reference?: string|null, from_register?: bool}  $data
     */
    public function handle(User $actor, array $data, ?Expense $expense = null): Expense
    {
        return DB::transaction(function () use ($actor, $data, $expense): Expense {
            $fromRegister = (bool) Arr::pull($data, 'from_register', false);

            if ($expense !== null) {
                if ($expense->cash_movement_id !== null && $this->changesMoney($expense, $data)) {
                    throw ApiException::conflict('expense_from_register');
                }

                $expense->update($data);
                activity('expense')->causedBy($actor)->performedOn($expense)->event('updated')->withProperties(['changes' => $expense->getChanges()])->log('updated');

                return $expense;
            }

            $expense = new Expense([...$data, 'created_by' => $actor->id]);

            if ($fromRegister) {
                if ($expense->method !== PaymentMethod::Cash) {
                    throw ApiException::conflict('expense_register_needs_cash');
                }

                $shift = CashShift::query()->where('branch_id', $expense->branch_id)->where('status', ShiftStatus::Open)->first()
                    ?? throw ApiException::conflict('shift_not_open');

                $movement = $this->recordCashMovement->handle($actor, $shift, CashMovementType::Out, $expense->amount, __('billing.expense_movement', ['description' => $expense->description]));
                $expense->cash_movement_id = $movement->id;
            }

            $expense->save();
            activity('expense')->causedBy($actor)->performedOn($expense)->event('created')
                ->withProperties(['amount' => $expense->amount, 'from_register' => $fromRegister])
                ->log('created');

            return $expense;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function changesMoney(Expense $expense, array $data): bool
    {
        return (isset($data['amount']) && (int) $data['amount'] !== $expense->amount)
            || (isset($data['branch_id']) && (int) $data['branch_id'] !== $expense->branch_id)
            || (isset($data['method']) && $data['method'] !== $expense->method->value);
    }
}
