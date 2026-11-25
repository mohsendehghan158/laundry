<?php

declare(strict_types=1);

namespace App\Modules\Production\Http\Resources;

use App\Modules\Orders\Models\Piece;
use App\Modules\Production\Models\Transfer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Transfer
 */
class TransferResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'status' => $this->status,
            /** @var array{id: int, name: string} */
            'from_branch' => ['id' => $this->fromBranch->id, 'name' => $this->fromBranch->name],
            /** @var array{id: int, name: string} */
            'to_branch' => ['id' => $this->toBranch->id, 'name' => $this->toBranch->name],
            'bags_sent' => $this->bags_sent,
            'bags_received' => $this->bags_received,
            'note' => $this->note,
            'piece_count' => (int) ($this->pieces_count ?? $this->pieces->count()),
            'received_count' => (int) ($this->received_count ?? $this->pieces->filter(fn (Piece $p): bool => $p->getRelationValue('pivot')?->received_at !== null)->count()),
            'creator_name' => $this->creator?->name,
            'sender_name' => $this->sender?->name,
            'sent_at' => $this->sent_at,
            'receiver_name' => $this->receiver?->name,
            'received_at' => $this->received_at,
            'created_at' => $this->created_at,
            /** @var list<array{id: int, tag_code: string, item_name: string, color_name: string|null, ticket_number: string, order_id: int, received: bool}> */
            'pieces' => $this->whenLoaded('pieces', fn (): array => $this->pieces->map(fn (Piece $piece): array => [
                'id' => $piece->id,
                'tag_code' => $piece->tag_code,
                'item_name' => $piece->line->item_name,
                'color_name' => $piece->color_name,
                'ticket_number' => $piece->order->ticket_number,
                'order_id' => $piece->order_id,
                'received' => $piece->getRelationValue('pivot')?->received_at !== null,
            ])->values()->all()),
        ];
    }
}
