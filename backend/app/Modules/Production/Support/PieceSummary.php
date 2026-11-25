<?php

declare(strict_types=1);

namespace App\Modules\Production\Support;

use App\Modules\Orders\Models\Piece;
use App\Modules\Orders\Models\PieceDefect;

/**
 * What a station shows about a scanned piece.
 *
 * @phpstan-type ScannedPiece array{id: int, tag_code: string, item_name: string, service_name: string, color_name: string|null, notes: string|null, defects: list<string>, status: string, flag: string|null, flag_note: string|null, order: array{id: int, ticket_number: string, due_at: string, is_express: bool, is_overdue: bool, piece_count: int, notes: string|null}}
 */
final class PieceSummary
{
    /**
     * @return ScannedPiece
     */
    public static function of(Piece $piece): array
    {
        $order = $piece->order;

        return [
            'id' => $piece->id,
            'tag_code' => $piece->tag_code,
            'item_name' => $piece->line->item_name,
            'service_name' => $piece->line->service_name,
            'color_name' => $piece->color_name,
            'notes' => $piece->notes,
            'defects' => $piece->defects->map(fn (PieceDefect $defect): string => $defect->name)->values()->all(),
            'status' => $piece->status->value,
            'flag' => $piece->flag?->value,
            'flag_note' => $piece->flag_note,
            'order' => [
                'id' => $order->id,
                'ticket_number' => $order->ticket_number,
                'due_at' => $order->due_at->toIso8601String(),
                'is_express' => $order->surcharge_percent > 0,
                'is_overdue' => $order->status->isOpen() && $order->due_at->isPast(),
                'piece_count' => $order->pieces()->count(),
                'notes' => $order->notes,
            ],
        ];
    }
}
