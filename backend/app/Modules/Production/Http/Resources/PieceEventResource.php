<?php

declare(strict_types=1);

namespace App\Modules\Production\Http\Resources;

use App\Modules\Production\Models\PieceScan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PieceScan
 */
class PieceEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event' => $this->event,
            /** @var array{id: int, name: string}|null */
            'station' => $this->station === null ? null : ['id' => $this->station->id, 'name' => $this->station->name],
            /** @var array{id: int, name: string}|null */
            'branch' => $this->branch === null ? null : ['id' => $this->branch->id, 'name' => $this->branch->name],
            'user_name' => $this->user?->name,
            'note' => $this->note,
            'created_at' => $this->created_at,
        ];
    }
}
