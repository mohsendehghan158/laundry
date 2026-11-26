<?php

declare(strict_types=1);

namespace App\Modules\Portal\Actions;

use App\Modules\Orders\Actions\TransitionOrder;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use App\Support\Exceptions\ApiException;
use Illuminate\Support\Facades\DB;

/**
 * The customer calls off their order before it is picked up; the booked
 * window gets its place back (see Logistics' ReleasePickupSlot). Fails
 * with 409 `order_not_cancellable` once the driver has it.
 */
final readonly class CancelOnlineOrder
{
    public function __construct(private TransitionOrder $transition) {}

    public function handle(Order $order): Order
    {
        // Locked, so two taps on "cancel" free the window's place only once.
        return DB::transaction(function () use ($order): Order {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ($locked->status !== OrderStatus::PendingPickup) {
                throw ApiException::conflict('order_not_cancellable');
            }

            return $this->transition->handle(null, $locked, OrderStatus::Cancelled, (string) __('portal.cancelled_by_customer'));
        });
    }
}
