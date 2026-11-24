<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Resources;

use App\Modules\Catalog\Models\TurnaroundTier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TurnaroundTier
 */
class TurnaroundTierResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'duration_hours' => $this->duration_hours,
            /** Added to line prices, e.g. 50 for +50%. */
            'surcharge_percent' => $this->surcharge_percent,
            'is_default' => $this->is_default,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
        ];
    }
}
