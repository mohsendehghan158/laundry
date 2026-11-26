<?php

declare(strict_types=1);

namespace App\Modules\Production\Support;

use App\Modules\Orders\Enums\PieceStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\Piece;
use App\Modules\Organization\Support\Settings;
use App\Modules\Production\Enums\PieceFlag;

/**
 * An order seen from the assembly table: which pieces are gathered, which
 * are still missing and why, and where to rack it.
 *
 * @phpstan-type AssemblyPiece array{id: int, tag_code: string, item_name: string, color_name: string|null, status: string, flag: string|null, flag_note: string|null, branch: array{id: int, name: string}|null, slot: string|null, blocker: string|null}
 * @phpstan-type AssemblyState array{order: array{id: int, ticket_number: string, status: string, due_at: string, is_express: bool, is_overdue: bool, customer_name: string, branch: array{id: int, name: string}, notes: string|null, rack_location: string|null}, pieces: list<AssemblyPiece>, counts: array{total: int, assembled: int, ready: int, waiting: int, lost: int}, complete: bool, suggestion: array{id: int, code: string}|null, scan_required: bool}
 */
final class Assembly
{
    public function __construct(private readonly RackSuggester $racks, private readonly Settings $settings) {}

    /**
     * Why a piece cannot be gathered now, or null when it can.
     */
    public static function blocker(Piece $piece, Order $order): ?string
    {
        return match (true) {
            $piece->flag !== null && in_array($piece->flag, [PieceFlag::OnHold, PieceFlag::Outsourced, PieceFlag::InTransit, PieceFlag::Lost, PieceFlag::Rework], true) => (string) __("production.blockers.{$piece->flag->value}", ['note' => $piece->flag_note ?? '—']),
            $piece->branch_id !== null && $piece->branch_id !== $order->branch_id => (string) __('production.blockers.elsewhere', ['branch' => $piece->branch->name ?? '—']),
            default => null,
        };
    }

    /**
     * @return AssemblyState
     */
    public function state(Order $order): array
    {
        $order->loadMissing(['customer', 'branch']);
        $pieces = $order->pieces()->with(['line', 'branch', 'rackSlot'])->orderBy('number')->get();

        $waiting = $pieces->filter(fn (Piece $p): bool => $p->status->inWork() && $p->flag !== PieceFlag::Lost);
        $assembled = $pieces->filter(fn (Piece $p): bool => $p->status === PieceStatus::Assembled);
        $suggestion = $order->status->isOpen() ? $this->racks->suggest($order) : null;

        return [
            'order' => [
                'id' => $order->id,
                'ticket_number' => $order->ticket_number,
                'status' => $order->status->value,
                'due_at' => $order->due_at->toIso8601String(),
                'is_express' => $order->surcharge_percent > 0,
                'is_overdue' => $order->status->isOpen() && $order->due_at->isPast(),
                'customer_name' => $order->customer->name,
                'branch' => ['id' => $order->branch->id, 'name' => $order->branch->name],
                'notes' => $order->notes,
                'rack_location' => $order->rack_location,
            ],
            'pieces' => array_values($pieces->map(fn (Piece $piece): array => [
                'id' => $piece->id,
                'tag_code' => $piece->tag_code,
                'item_name' => $piece->line->item_name,
                'color_name' => $piece->color_name,
                'status' => $piece->status->value,
                'flag' => $piece->flag?->value,
                'flag_note' => $piece->flag_note,
                'branch' => $piece->branch === null ? null : ['id' => $piece->branch->id, 'name' => $piece->branch->name],
                'slot' => $piece->rackSlot?->code,
                'blocker' => $piece->status->inWork() ? self::blocker($piece, $order) : null,
            ])->all()),
            'counts' => [
                'total' => $pieces->count(),
                'assembled' => $assembled->count(),
                'ready' => $pieces->filter(fn (Piece $p): bool => $p->status === PieceStatus::Ready)->count(),
                'waiting' => $waiting->count(),
                'lost' => $pieces->filter(fn (Piece $p): bool => $p->flag === PieceFlag::Lost && $p->status !== PieceStatus::HandedOver)->count(),
            ],
            'complete' => $waiting->isEmpty() && $assembled->isNotEmpty(),
            'suggestion' => $suggestion === null ? null : ['id' => $suggestion->id, 'code' => $suggestion->code],
            'scan_required' => (bool) $this->settings->get('production.assembly_scan_required'),
        ];
    }
}
