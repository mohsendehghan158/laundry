<?php

declare(strict_types=1);

namespace App\Modules\Logistics\Http\Resources;

use App\Modules\Logistics\Models\SlotTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SlotTemplate
 */
class SlotTemplateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'branch_id' => $this->branch_id,
            /** 0 = Sunday … 6 = Saturday. */
            'weekday' => $this->weekday,
            /** Local time, "09:00". */
            'starts_at' => substr($this->starts_at, 0, 5),
            'ends_at' => substr($this->ends_at, 0, 5),
            'capacity' => $this->capacity,
            'is_active' => $this->is_active,
        ];
    }
}
