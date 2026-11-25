<?php

declare(strict_types=1);

namespace App\Modules\Production\Http\Resources;

use App\Modules\Orders\Models\Piece;
use App\Modules\Production\Models\OutsourcingShipment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OutsourcingShipment
 */
class ShipmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $returned = $this->pieces->filter(fn (Piece $p): bool => $p->getRelationValue('pivot')?->returned_at !== null)->count();

        return [
            'id' => $this->id,
            'number' => $this->number,
            'status' => $this->status,
            /** @var array{id: int, name: string, phone: string|null} */
            'vendor' => ['id' => $this->vendor->id, 'name' => $this->vendor->name, 'phone' => $this->vendor->phone],
            /** @var array{id: int, name: string} */
            'branch' => ['id' => $this->branch->id, 'name' => $this->branch->name],
            'expected_at' => $this->expected_at,
            'is_late' => $this->closed_at === null && $this->expected_at !== null && $this->expected_at->isPast(),
            'cost' => $this->cost,
            'note' => $this->note,
            'piece_count' => $this->pieces->count(),
            'returned_count' => $returned,
            'sender_name' => $this->sender?->name,
            'sent_at' => $this->sent_at,
            'closed_at' => $this->closed_at,
            /** @var list<array{id: int, tag_code: string, item_name: string, ticket_number: string, order_id: int, returned: bool}> */
            'pieces' => $this->pieces->map(fn (Piece $piece): array => [
                'id' => $piece->id,
                'tag_code' => $piece->tag_code,
                'item_name' => $piece->line->item_name,
                'ticket_number' => $piece->order->ticket_number,
                'order_id' => $piece->order_id,
                'returned' => $piece->getRelationValue('pivot')?->returned_at !== null,
            ])->values()->all(),
        ];
    }
}
