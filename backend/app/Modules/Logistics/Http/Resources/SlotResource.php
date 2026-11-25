<?php

declare(strict_types=1);

namespace App\Modules\Logistics\Http\Resources;

use App\Modules\Logistics\Models\Slot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A dated slot as staff see it, bookings included.
 *
 * @mixin Slot
 */
class SlotResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'branch_id' => $this->branch_id,
            /** Local date, "2026-12-05". */
            'date' => $this->date->toDateString(),
            /** Local times, "09:00". */
            'starts_at' => substr($this->starts_at, 0, 5),
            'ends_at' => substr($this->ends_at, 0, 5),
            'capacity' => $this->capacity,
            'booked' => $this->booked,
            'remaining' => $this->remaining(),
            'is_closed' => $this->is_closed,
            /** Made from a weekly template (false once that template is gone). */
            'from_template' => $this->slot_template_id !== null,
        ];
    }
}
