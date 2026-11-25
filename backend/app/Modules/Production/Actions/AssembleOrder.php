<?php

declare(strict_types=1);

namespace App\Modules\Production\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Orders\Actions\TransitionOrder;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PieceStatus;
use App\Modules\Orders\Events\OrderStatusChanged;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\Piece;
use App\Modules\Organization\Support\Settings;
use App\Modules\Production\Enums\PieceEvent;
use App\Modules\Production\Enums\PieceFlag;
use App\Modules\Production\Models\RackSlot;
use App\Modules\Production\Support\Assembly;
use App\Modules\Production\Support\PieceHistory;
use App\Support\Exceptions\ApiException;
use Illuminate\Support\Facades\DB;

/**
 * Gathers an order's pieces and puts the order on the rack. An order is
 * ready only once every piece (except lost ones) has been gathered; with
 * `partial`, the gathered pieces can be racked while the rest are still
 * being worked on (the order is then partially ready).
 */
final class AssembleOrder
{
    public function __construct(private readonly TransitionOrder $transition, private readonly Settings $settings) {}

    /**
     * Gathers one piece, scanned at the assembly table. The first piece
     * gathered puts a just-received order in process.
     */
    public function piece(User $actor, Piece $piece, bool $startOrder = true): Piece
    {
        return DB::transaction(function () use ($actor, $piece, $startOrder): Piece {
            $piece = Piece::query()->with(['order', 'branch'])->lockForUpdate()->findOrFail($piece->id);
            $order = $piece->order;

            if (! in_array($order->status, [OrderStatus::Received, OrderStatus::InProcess, OrderStatus::PartiallyReady], true)) {
                throw ApiException::conflict('assembly_order_not_open');
            }
            if ($piece->status === PieceStatus::Assembled) {
                return $piece;
            }
            if (! $piece->status->inWork()) {
                throw ApiException::conflict('assembly_piece_done', ['tag' => $piece->tag_code]);
            }

            $blocker = Assembly::blocker($piece, $order);
            if ($blocker !== null) {
                throw new ApiException('assembly_piece_blocked', $piece->tag_code.': '.$blocker);
            }

            $piece->update(['status' => PieceStatus::Assembled, 'assembled_at' => now(), 'last_scanned_at' => now()]);
            PieceHistory::record($piece, PieceEvent::Assembled, $actor);

            if ($startOrder && $order->status === OrderStatus::Received) {
                $this->transition->handle($actor, $order, OrderStatus::InProcess);
            }

            return $piece;
        });
    }

    /**
     * Gathers the listed pieces (ticked off by hand, unless the
     * organization requires scanning) and racks everything gathered.
     *
     * @param  list<int>  $pieceIds
     */
    public function complete(User $actor, Order $order, array $pieceIds, ?RackSlot $slot, ?string $location, bool $partial): Order
    {
        return DB::transaction(function () use ($actor, $order, $pieceIds, $slot, $location, $partial): Order {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);

            if (! in_array($order->status, [OrderStatus::Received, OrderStatus::InProcess, OrderStatus::PartiallyReady], true)) {
                throw ApiException::conflict('assembly_order_not_open');
            }

            if ($pieceIds !== []) {
                if ($this->settings->get('production.assembly_scan_required')) {
                    throw ApiException::conflict('assembly_scan_required');
                }
                // Ticked off at the counter: straight from received to ready, no "in process" in between.
                foreach ($order->pieces()->whereIn('id', $pieceIds)->get() as $piece) {
                    $this->piece($actor, $piece, startOrder: false);
                }
                $order->refresh();
            }

            $pieces = $order->pieces()->get();
            $assembled = $pieces->filter(fn (Piece $p): bool => $p->status === PieceStatus::Assembled);
            $waiting = $pieces->filter(fn (Piece $p): bool => $p->status->inWork() && $p->flag !== PieceFlag::Lost);

            if ($assembled->isEmpty()) {
                throw ApiException::conflict('assembly_nothing_gathered');
            }
            if ($waiting->isNotEmpty() && ! $partial) {
                throw ApiException::conflict('assembly_incomplete', ['tags' => $waiting->pluck('tag_code')->implode('، ')]);
            }

            $now = now();
            $code = $slot !== null ? $slot->code : ($location === null || $location === '' ? null : $location);
            foreach ($assembled as $piece) {
                $piece->update(['status' => PieceStatus::Ready, 'ready_at' => $now, 'rack_slot_id' => $slot?->id]);
                PieceHistory::record($piece, PieceEvent::Racked, $actor, note: $code);
            }

            $from = $order->status;
            $to = $waiting->isEmpty() ? OrderStatus::Ready : OrderStatus::PartiallyReady;
            $locations = array_values(array_unique(array_filter([...explode('، ', (string) $order->rack_location), $code])));

            $order->update([
                'status' => $to,
                'ready_at' => $to === OrderStatus::Ready ? $now : $order->ready_at,
                'rack_location' => $locations === [] ? null : mb_substr(implode('، ', $locations), 0, 50),
                'version' => $order->version + 1,
            ]);

            if ($from !== $to) {
                $order->history()->create(['from_status' => $from, 'to_status' => $to, 'user_id' => $actor->id]);
                OrderStatusChanged::dispatch($order, $from, $to);
            }

            return $order;
        });
    }
}
