<?php

declare(strict_types=1);

namespace App\Modules\Production\Http\Resources;

use App\Modules\Orders\Models\Piece;
use App\Modules\Orders\Models\PieceDefect;
use App\Modules\Production\Http\Controllers\PhotoController;
use App\Modules\Production\Models\Claim;
use App\Modules\Production\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

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
            'rack_location' => $this->status->value === 'ready' ? ($this->rackSlot->code ?? $order->rack_location) : null,
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
            /** @var list<array{id: int, url: string, uploader_name: string|null, created_at: string}> */
            'photos' => $this->photos->map(fn (Media $media): array => PhotoController::describe($media))->values()->all(),
            /** @var list<array{id: int, number: string, kind: string, status: string, description: string}> */
            'claims' => $this->claims->map(fn (Claim $claim): array => ['id' => $claim->id, 'number' => $claim->number, 'kind' => $claim->kind->value, 'status' => $claim->status->value, 'description' => $claim->description])->values()->all(),
            /** On a transfer or at a vendor right now. @var array{kind: 'transfer'|'shipment', id: int, number: string, to: string}|null */
            'movement' => $this->movement(),
        ];
    }

    /**
     * @return array{kind: 'transfer'|'shipment', id: int, number: string, to: string}|null
     */
    private function movement(): ?array
    {
        $transfer = DB::table('transfer_pieces')
            ->join('transfers', 'transfers.id', '=', 'transfer_pieces.transfer_id')
            ->join('branches', 'branches.id', '=', 'transfers.to_branch_id')
            ->where('transfer_pieces.piece_id', $this->id)
            ->whereNull('transfer_pieces.received_at')
            ->whereIn('transfers.status', ['open', 'sent', 'received'])
            ->orderByDesc('transfers.id')
            ->first(['transfers.id', 'transfers.number', 'branches.name']);

        if ($transfer !== null) {
            return ['kind' => 'transfer', 'id' => (int) $transfer->id, 'number' => (string) $transfer->number, 'to' => (string) $transfer->name];
        }

        $shipment = DB::table('outsourcing_shipment_pieces')
            ->join('outsourcing_shipments', 'outsourcing_shipments.id', '=', 'outsourcing_shipment_pieces.outsourcing_shipment_id')
            ->join('vendors', 'vendors.id', '=', 'outsourcing_shipments.vendor_id')
            ->where('outsourcing_shipment_pieces.piece_id', $this->id)
            ->whereNull('outsourcing_shipment_pieces.returned_at')
            ->orderByDesc('outsourcing_shipments.id')
            ->first(['outsourcing_shipments.id', 'outsourcing_shipments.number', 'vendors.name']);

        return $shipment === null ? null : ['kind' => 'shipment', 'id' => (int) $shipment->id, 'number' => (string) $shipment->number, 'to' => (string) $shipment->name];
    }
}
