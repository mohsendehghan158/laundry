<?php

declare(strict_types=1);

namespace App\Modules\Production\Http\Resources;

use App\Modules\Orders\Models\Piece;
use App\Modules\Orders\Models\PieceDefect;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A piece with where it is, its order and its history.
 *
 * @mixin Piece
 */
class PieceDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $order = $this->order;

        return [
            'id' => $this->id,
            'tag_code' => $this->tag_code,
            'number' => $this->number,
            'status' => $this->status,
            /** Side state: rework, on_hold, outsourced, in_transit, damaged or lost. */
            'flag' => $this->flag,
            'flag_note' => $this->flag_note,
            'item_name' => $this->line->item_name,
            'service_name' => $this->line->service_name,
            'color_name' => $this->color_name,
            'brand' => $this->brand,
            'notes' => $this->notes,
            /** @var list<string> */
            'defects' => $this->defects->map(fn (PieceDefect $defect): string => $defect->name)->values()->all(),
            /** @var array{id: int, name: string}|null */
            'branch' => $this->branch === null ? null : ['id' => $this->branch->id, 'name' => $this->branch->name],
            /** @var array{id: int, name: string}|null */
            'station' => $this->station === null ? null : ['id' => $this->station->id, 'name' => $this->station->name],
            /** Where it waits on the rack, once ready. */
            'rack_location' => in_array($this->status->value, ['ready'], true) ? $order->rack_location : null,
            'last_scanned_at' => $this->last_scanned_at,
            'assembled_at' => $this->assembled_at,
            'ready_at' => $this->ready_at,
            'handed_over_at' => $this->handed_over_at,
            /** @var array{id: int, ticket_number: string, status: string, due_at: string, is_express: bool, is_overdue: bool, piece_count: int, customer_name: string, customer_mobile: string, branch: array{id: int, name: string}} */
            'order' => [
                'id' => $order->id,
                'ticket_number' => $order->ticket_number,
                'status' => $order->status->value,
                'due_at' => $order->due_at->toIso8601String(),
                'is_express' => $order->surcharge_percent > 0,
                'is_overdue' => $order->status->isOpen() && $order->due_at->isPast(),
                'piece_count' => (int) $order->pieces_count,
                'customer_name' => $order->customer->name,
                'customer_mobile' => $order->customer->mobile,
                'branch' => ['id' => $order->branch->id, 'name' => $order->branch->name],
            ],
            'events' => PieceEventResource::collection($this->events),
        ];
    }
}
