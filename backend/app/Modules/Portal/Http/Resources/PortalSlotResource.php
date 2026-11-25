<?php

declare(strict_types=1);

namespace App\Modules\Portal\Http\Resources;

use App\Modules\Logistics\Models\Slot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A slot a customer can book.
 *
 * @mixin Slot
 */
class PortalSlotResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            /** Local date, "2026-12-05". */
            'date' => $this->date->toDateString(),
            /** Local times, "09:00". */
            'starts_at' => substr($this->starts_at, 0, 5),
            'ends_at' => substr($this->ends_at, 0, 5),
            /** Places left. */
            'remaining' => $this->remaining(),
        ];
    }
}
