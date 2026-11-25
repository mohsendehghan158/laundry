<?php

declare(strict_types=1);

namespace App\Modules\Orders\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PieceStatus;
use App\Modules\Orders\Models\Order;
use App\Support\Exceptions\ApiException;
use Illuminate\Support\Facades\DB;

final class HandOverOrder
{
    /**
     * Gives ready pieces back to the customer: all of them, or some (the
     * rest stays on the rack). The order is completed once every piece
     * has been handed over. An unpaid balance blocks handover unless the
     * user may release orders on credit (orders.handover_unpaid).
     *
     * @param  list<int>  $pieceIds
     */
    public function handle(User $actor, Order $order, array $pieceIds): Order
    {
        return DB::transaction(function () use ($actor, $order, $pieceIds): Order {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);

            if (! in_array($order->status, [OrderStatus::Ready, OrderStatus::PartiallyReady], true)) {
                throw ApiException::conflict('handover_not_ready');
            }

            if ($order->balance() > 0 && ! $actor->can('orders.handover_unpaid')) {
                throw ApiException::conflict('handover_unpaid');
            }

            $pieces = $order->pieces()->whereIn('id', $pieceIds)->get();

            if ($pieces->count() !== count(array_unique($pieceIds)) || $pieces->contains(fn ($piece) => $piece->status !== PieceStatus::Ready)) {
                throw ApiException::conflict('handover_piece_unavailable');
            }

            $now = now();
            $order->pieces()->whereIn('id', $pieceIds)->update(['status' => PieceStatus::HandedOver, 'handed_over_at' => $now]);

            $remaining = $order->pieces()->where('status', '!=', PieceStatus::HandedOver)->count();
            $changes = ['version' => $order->version + 1];

            if ($remaining === 0) {
                $changes += ['status' => OrderStatus::Completed, 'completed_at' => $now];
                $order->history()->create(['from_status' => $order->status, 'to_status' => OrderStatus::Completed, 'user_id' => $actor->id]);
            }

            $order->update($changes);

            activity('order')->causedBy($actor)->performedOn($order)->event('handed_over')
                ->withProperties([
                    'pieces' => $pieces->pluck('tag_code')->all(),
                    'remaining' => $remaining,
                    'balance' => $order->balance(),
                ])
                ->log('handed_over');

            return $order;
        });
    }
}
