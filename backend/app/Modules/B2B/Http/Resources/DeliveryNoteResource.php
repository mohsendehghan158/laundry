<?php

declare(strict_types=1);

namespace App\Modules\B2B\Http\Resources;

use App\Modules\B2B\Models\DeliveryNote;
use App\Modules\B2B\Models\DeliveryNoteLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DeliveryNote
 */
class DeliveryNoteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'type' => $this->type,
            'status' => $this->status,
            /** @var array{id: int, name: string} */
            'company' => ['id' => $this->company->id, 'name' => $this->company->name],
            /** @var array{id: int, name: string, code: string|null}|null */
            'department' => $this->department === null ? null : ['id' => $this->department->id, 'name' => $this->department->name, 'code' => $this->department->code],
            /** @var array{id: int, name: string} */
            'branch' => ['id' => $this->branch->id, 'name' => $this->branch->name],
            /** @var array{id: int, name: string, mobile: string} */
            'customer' => ['id' => $this->customer->id, 'name' => $this->customer->name, 'mobile' => $this->customer->mobile],
            /** @var array{id: int, ticket_number: string}|null In: the order made from it. Out: the order sent back. */
            'order' => $this->order === null ? null : ['id' => $this->order->id, 'ticket_number' => $this->order->ticket_number],
            'counterpart_name' => $this->counterpart_name,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'confirmed_at' => $this->confirmed_at,
            /** @var array{id: int, name: string}|null */
            'created_by' => $this->creator === null ? null : ['id' => $this->creator->id, 'name' => $this->creator->name],
            /** Lines whose counts differ. */
            'differences' => $this->whenLoaded('lines', fn (): int => $this->lines->filter(fn (DeliveryNoteLine $line): bool => $line->difference() !== 0.0)->count()),
            /** @var list<array{id: int, item_id: int|null, service_type_id: int|null, order_line_id: int|null, item_name: string, service_name: string|null, expected_quantity: float, counted_quantity: float, difference: float}> */
            'lines' => $this->whenLoaded('lines', fn (): array => $this->lines->map(fn (DeliveryNoteLine $line): array => [
                'id' => $line->id,
                'item_id' => $line->item_id,
                'service_type_id' => $line->service_type_id,
                'order_line_id' => $line->order_line_id,
                'item_name' => $line->item_name,
                'service_name' => $line->service_name,
                'expected_quantity' => (float) $line->expected_quantity,
                'counted_quantity' => (float) $line->counted_quantity,
                'difference' => $line->difference(),
            ])->all()),
        ];
    }
}
