<?php

declare(strict_types=1);

namespace App\Modules\Orders\Http\Resources;

use App\Modules\Orders\Models\OrderLine;
use App\Modules\Orders\Models\OrderLineUpcharge;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Amounts are whole rials.
 *
 * @mixin OrderLine
 */
class OrderLineResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'item_id' => $this->item_id,
            'service_type_id' => $this->service_type_id,
            'item_name' => $this->item_name,
            'service_name' => $this->service_name,
            'service_code' => $this->service_code,
            'unit' => $this->unit,
            /** @var float Pieces, kilograms or square metres. */
            'quantity' => (float) $this->quantity,
            'unit_price' => $this->unit_price,
            'base_amount' => $this->base_amount,
            'upcharge_amount' => $this->upcharge_amount,
            /** Turnaround surcharge of this line. */
            'surcharge_amount' => $this->surcharge_amount,
            'line_total' => $this->line_total,
            'notes' => $this->notes,
            /** @var list<array{upcharge_id: int|null, name: string, type: 'fixed'|'percent', value: int, amount: int}> */
            'upcharges' => $this->upcharges->map(fn (OrderLineUpcharge $upcharge): array => [
                'upcharge_id' => $upcharge->upcharge_id,
                'name' => $upcharge->name,
                'type' => $upcharge->type->value,
                'value' => $upcharge->value,
                'amount' => $upcharge->amount,
            ])->all(),
            'pieces' => PieceResource::collection($this->whenLoaded('pieces')),
        ];
    }
}
