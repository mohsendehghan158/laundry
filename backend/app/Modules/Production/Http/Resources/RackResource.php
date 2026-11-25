<?php

declare(strict_types=1);

namespace App\Modules\Production\Http\Resources;

use App\Modules\Production\Models\Rack;
use App\Modules\Production\Models\RackSlot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Rack
 */
class RackResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'branch_id' => $this->branch_id,
            'name' => $this->name,
            'code' => $this->code,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            /** @var list<array{id: int, code: string, orders: list<string>}> Ticket numbers of the orders waiting in each slot. */
            'slots' => $this->slots->map(fn (RackSlot $slot): array => [
                'id' => $slot->id,
                'code' => $slot->code,
                'orders' => array_values(array_unique($slot->pieces->map(fn ($piece): string => $piece->order->ticket_number)->all())),
            ])->all(),
        ];
    }
}
