<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Resources;

use App\Modules\Billing\Models\CashMovement;
use App\Modules\Billing\Models\CashShift;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Amounts are whole rials.
 *
 * @mixin CashShift
 */
class CashShiftResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            /** @var array{id: int, name: string, code: string} */
            'branch' => ['id' => $this->branch->id, 'name' => $this->branch->name, 'code' => $this->branch->code],
            'opened_at' => $this->opened_at,
            /** @var array{id: int, name: string}|null */
            'opened_by' => $this->opener === null ? null : ['id' => $this->opener->id, 'name' => $this->opener->name],
            'opening_float' => $this->opening_float,
            'closed_at' => $this->closed_at,
            /** @var array{id: int, name: string}|null */
            'closed_by' => $this->closer === null ? null : ['id' => $this->closer->id, 'name' => $this->closer->name],
            /** Cash that should be in the drawer at closing. */
            'expected_cash' => $this->expected_cash,
            'counted_cash' => $this->counted_cash,
            /** Counted minus expected; negative means cash is missing. */
            'difference' => $this->difference,
            'notes' => $this->notes,
            /** @var array{by_method: array{cash: int, card: int, transfer: int}, payments: int, refunds: int, cash_in: int, cash_out: int, expected_cash: int} Live totals of the shift. */
            'totals' => $this->totals(),
            'payments' => PaymentResource::collection($this->whenLoaded('payments')),
            /** @var list<array{id: int, type: 'in'|'out', amount: int, reason: string, user: array{id: int, name: string}|null, created_at: string}> */
            'movements' => $this->whenLoaded('movements', fn (): array => $this->movements->map(fn (CashMovement $movement): array => [
                'id' => $movement->id,
                'type' => $movement->type->value,
                'amount' => $movement->amount,
                'reason' => $movement->reason,
                'user' => $movement->user === null ? null : ['id' => $movement->user->id, 'name' => $movement->user->name],
                'created_at' => $movement->created_at->toIso8601String(),
            ])->all()),
        ];
    }
}
