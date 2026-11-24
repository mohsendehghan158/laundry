<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Resources;

use App\Modules\Catalog\Models\Upcharge;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Upcharge
 */
class UpchargeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            /** Rials when fixed, whole percent when percent. */
            'value' => $this->value,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
        ];
    }
}
