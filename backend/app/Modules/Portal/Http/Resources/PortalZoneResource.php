<?php

declare(strict_types=1);

namespace App\Modules\Portal\Http\Resources;

use App\Modules\Logistics\Models\Zone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What a customer sees of the zone their address is in: its name and
 * what delivery costs.
 *
 * @mixin Zone
 */
class PortalZoneResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'branch_name' => $this->branch->name,
            /** Rials. */
            'delivery_fee' => $this->delivery_fee,
            'free_delivery_over' => $this->free_delivery_over,
            'min_order' => $this->min_order,
        ];
    }
}
