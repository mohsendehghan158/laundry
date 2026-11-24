<?php

declare(strict_types=1);

namespace App\Modules\Orders\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PieceStatus;
use App\Modules\Orders\Models\Order;
use App\Support\Exceptions\ApiException;
use Illuminate\Support\Facades\DB;

final class TransitionOrder
{
    /**
     * Moves an order to another status if the state machine allows it,
     * keeps its pieces in step and appends to the status history.
     */
    public function handle(User $actor, Order $order, OrderStatus $to, ?string $reason = null, ?string $rackLocation = null): Order
    {
        if (! $order->status->canMoveTo($to)) {
            throw ApiException::conflict('order_transition_not_allowed', [
                'from' => __("orders.statuses.{$order->status->value}"),
                'to' => __("orders.statuses.{$to->value}"),
            ]);
        }

        if ($to === OrderStatus::Cancelled && $order->paid_amount > 0) {
            throw ApiException::conflict('order_has_payments');
        }

        return DB::transaction(function () use ($actor, $order, $to, $reason, $rackLocation): Order {
            $from = $order->status;
            $now = now();

            $changes = ['status' => $to, 'version' => $order->version + 1];

            if ($to === OrderStatus::Ready) {
                $changes['ready_at'] = $now;
                $changes['rack_location'] = $rackLocation ?? $order->rack_location;
                $order->pieces()->where('status', PieceStatus::Received)->update(['status' => PieceStatus::Ready, 'ready_at' => $now]);
            }

            if ($to === OrderStatus::InProcess && $from === OrderStatus::Ready) {
                // Sent back to the workshop (e.g. not clean enough).
                $changes['ready_at'] = null;
                $order->pieces()->where('status', PieceStatus::Ready)->update(['status' => PieceStatus::Received, 'ready_at' => null]);
            }

            if ($to === OrderStatus::Cancelled) {
                $changes['cancelled_at'] = $now;
                $changes['cancel_reason'] = $reason;
            }

            $order->update($changes);
            $order->history()->create(['from_status' => $from, 'to_status' => $to, 'user_id' => $actor->id, 'reason' => $reason]);

            if ($to === OrderStatus::Cancelled) {
                activity('order')->causedBy($actor)->performedOn($order)->event('cancelled')
                    ->withProperties(['reason' => $reason, 'total' => $order->total])
                    ->log('cancelled');
            }

            return $order;
        });
    }
}
