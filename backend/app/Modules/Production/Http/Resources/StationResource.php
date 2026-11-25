<?php

declare(strict_types=1);

namespace App\Modules\Production\Http\Resources;

use App\Modules\Production\Models\Station;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Station
 */
class StationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'branch_id' => $this->branch_id,
            'name' => $this->name,
            'code' => $this->code,
            'kind' => $this->kind,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
        ];
    }
}
