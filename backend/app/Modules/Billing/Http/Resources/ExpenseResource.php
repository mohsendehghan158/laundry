<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Resources;

use App\Modules\Billing\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Expense
 */
class ExpenseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            /** @var array{id: int, name: string} */
            'branch' => ['id' => $this->branch->id, 'name' => $this->branch->name],
            /** @var array{id: int, name: string} */
            'category' => ['id' => $this->category->id, 'name' => $this->category->name],
            /** Local date, Y-m-d. */
            'spent_on' => $this->spent_on->format('Y-m-d'),
            'amount' => $this->amount,
            'method' => $this->method,
            'description' => $this->description,
            'reference' => $this->reference,
            /** The cash came out of the register; branch, amount and method are then fixed. */
            'from_register' => $this->cash_movement_id !== null,
            /** @var array{id: int, name: string}|null */
            'created_by' => $this->whenLoaded('creator', fn (): ?array => $this->creator === null ? null : ['id' => $this->creator->id, 'name' => $this->creator->name]),
            'created_at' => $this->created_at,
        ];
    }
}
