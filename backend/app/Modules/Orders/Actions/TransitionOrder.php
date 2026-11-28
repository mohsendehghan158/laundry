<?php

declare(strict_types=1);

namespace App\Modules\Orders\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PieceStatus;
use App\Modules\Orders\Events\OrderStatusChanged;
use App\Modules\Orders\Models\Order;
use App\Modules\Production\Enums\PieceEvent;
use App\Modules\Production\Support\PieceHistory;
use App\Support\Exceptions\ApiException;
use Illuminate\Support\Facades\DB;

final class TransitionOrder
{
    /**
     * Moves an order to another status if the state machine allows it,
     * keeps its pieces in step and appends to the status history. The
     * actor is null when the customer does it in the portal.
     */
    public function handle(?User $actor, Order $order, OrderStatus $to, ?string $reason = null): Order
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

        if ($to === OrderStatus::Cancelled && $order->isInvoiced()) {
            throw ApiException::conflict('order_invoiced');
        }

        return DB::transaction(function () use ($actor, $order, $to, $reason): Order {
            $from = $order->status;
            $now = now();

            $changes = ['status' => $to, 'version' => $order->version + 1];

            if ($to === OrderStatus::InProcess && in_array($from, [OrderStatus::Ready, OrderStatus::PartiallyReady], true)) {
                // Sent back to the workshop (not clean enough, say): off the rack, to be gathered again.
                $changes += ['ready_at' => null, 'rack_location' => null];
                foreach ($order->pieces()->whereIn('status', [PieceStatus::Ready, PieceStatus::Assembled])->get() as $piece) {
                    $piece->update(['status' => PieceStatus::InProcess, 'ready_at' => null, 'assembled_at' => null, 'rack_slot_id' => null]);
                    PieceHistory::record($piece, PieceEvent::SentBack, $actor, note: $reason);
                }
            }

            if ($to === OrderStatus::Cancelled) {
                $changes['cancelled_at'] = $now;
                $changes['cancel_reason'] = $reason;
            }

            $order->update($changes);
            $order->history()->create(['from_status' => $from, 'to_status' => $to, 'user_id' => $actor?->id, 'reason' => $reason]);
            OrderStatusChanged::dispatch($order, $from, $to);

            if ($to === OrderStatus::Cancelled) {
                activity('order')->causedBy($actor)->performedOn($order)->event('cancelled')
                    ->withProperties(['reason' => $reason, 'total' => $order->total])
                    ->log('cancelled');
            }

            return $order;
        });
    }
}
