<?php

declare(strict_types=1);

namespace App\Modules\Orders\Http\Resources;

use App\Modules\Orders\Models\Piece;
use App\Modules\Orders\Models\PieceDefect;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Piece
 */
class PieceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            /** Printed as a barcode on the piece's tag. */
            'tag_code' => $this->tag_code,
            /** Position of the piece within the order, from 1. */
            'number' => $this->number,
            'status' => $this->status,
            'color_id' => $this->color_id,
            'color_name' => $this->color_name,
            'brand' => $this->brand,
            'notes' => $this->notes,
            /** @var list<array{defect_type_id: int|null, name: string, kind: 'defect'|'stain'}> */
            'defects' => $this->defects->map(fn (PieceDefect $defect): array => [
                'defect_type_id' => $defect->defect_type_id,
                'name' => $defect->name,
                'kind' => $defect->kind->value,
            ])->all(),
            'ready_at' => $this->ready_at,
            'handed_over_at' => $this->handed_over_at,
        ];
    }
}
