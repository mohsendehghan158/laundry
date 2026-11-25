<?php

declare(strict_types=1);

namespace App\Modules\Production\Support;

use App\Modules\Orders\Enums\PieceStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\Piece;
use App\Modules\Production\Models\RackSlot;
use Illuminate\Support\Collection;

final class RackSuggester
{
    /**
     * Where to put an order's finished pieces at its pickup branch: the
     * slot its other pieces already wait in, else the first empty slot,
     * else the one holding the fewest orders. Null when the branch has
     * no racks.
     */
    public function suggest(Order $order): ?RackSlot
    {
        $slots = $this->slots($order->branch_id);

        if ($slots->isEmpty()) {
            return null;
        }

        $own = Piece::query()->where('order_id', $order->id)->where('status', PieceStatus::Ready)->whereNotNull('rack_slot_id')->value('rack_slot_id');

        return $slots->firstWhere('id', $own)
            ?? $slots->firstWhere('orders_count', 0)
            ?? $slots->sortBy('orders_count')->first();
    }

    /**
     * Active slots of a branch in rack order, each with how many orders wait in it.
     *
     * @return Collection<int, RackSlot>
     */
    public function slots(int $branchId): Collection
    {
        return RackSlot::query()
            ->join('racks', 'racks.id', '=', 'rack_slots.rack_id')
            ->where('racks.branch_id', $branchId)
            ->where('racks.is_active', true)
            ->whereNull('racks.deleted_at')
            ->select('rack_slots.*')
            ->addSelect(['orders_count' => Piece::query()
                ->selectRaw('count(distinct order_id)')
                ->whereColumn('pieces.rack_slot_id', 'rack_slots.id')
                ->where('pieces.status', PieceStatus::Ready)])
            ->orderBy('racks.sort_order')
            ->orderBy('racks.id')
            ->orderBy('rack_slots.position')
            ->get();
    }
}
