<?php

declare(strict_types=1);

namespace App\Modules\Production\Http\Resources;

use App\Modules\Production\Models\Claim;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Claim
 */
class ClaimResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'kind' => $this->kind,
            'status' => $this->status,
            'description' => $this->description,
            'resolution' => $this->resolution,
            /** Rials paid to the customer. */
            'compensation' => $this->compensation,
            /** @var array{id: int, ticket_number: string, customer_name: string, customer_mobile: string} */
            'order' => ['id' => $this->order->id, 'ticket_number' => $this->order->ticket_number, 'customer_name' => $this->order->customer->name, 'customer_mobile' => $this->order->customer->mobile],
            /** @var array{id: int, tag_code: string, item_name: string, color_name: string|null} */
            'piece' => ['id' => $this->piece->id, 'tag_code' => $this->piece->tag_code, 'item_name' => $this->piece->line->item_name, 'color_name' => $this->piece->color_name],
            'creator_name' => $this->creator?->name,
            'resolver_name' => $this->resolver?->name,
            'resolved_at' => $this->resolved_at,
            'created_at' => $this->created_at,
        ];
    }
}
