<?php

declare(strict_types=1);

namespace App\Modules\Production\Listeners;

use App\Modules\Orders\Events\OrderStatusChanged;
use App\Modules\Production\Support\Board;

final class RefreshBoards
{
    /** An order was created, started, racked, handed over or cancelled. */
    public function handle(OrderStatusChanged $event): void
    {
        $order = $event->order;
        $branches = array_values($order->pieces()->reorder()->distinct()->pluck('branch_id')->map(fn (mixed $id): ?int => $id === null ? null : (int) $id)->all());

        Board::changed([$order->branch_id, ...$branches], 'order', $order->id);
    }
}
