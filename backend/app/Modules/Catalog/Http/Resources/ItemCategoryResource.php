<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Resources;

use App\Modules\Catalog\Models\ItemCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ItemCategory
 */
class ItemCategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'icon' => $this->icon,
            'color' => $this->color,
            /** Present only in the counter catalog (GET /catalog). */
            'items' => ItemResource::collection($this->whenLoaded('items')),
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
        ];
    }
}
