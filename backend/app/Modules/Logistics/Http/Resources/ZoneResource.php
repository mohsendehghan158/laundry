<?php

declare(strict_types=1);

namespace App\Modules\Logistics\Http\Resources;

use App\Modules\Logistics\Models\Zone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Zone
 */
class ZoneResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'branch_id' => $this->branch_id,
            'branch_name' => $this->branch?->name,
            'name' => $this->name,
            /** @var list<array{0: float, 1: float}> Corners as [latitude, longitude]. */
            'polygon' => $this->polygon,
            /** Rials. */
            'delivery_fee' => $this->delivery_fee,
            'free_delivery_over' => $this->free_delivery_over,
            'min_order' => $this->min_order,
            'is_active' => $this->is_active,
        ];
    }
}
