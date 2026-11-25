<?php

declare(strict_types=1);

namespace App\Modules\Orders\Http\Controllers;

use App\Modules\Orders\Actions\TransitionOrder;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Http\Requests\TransitionOrderRequest;
use App\Modules\Orders\Http\Resources\OrderResource;
use App\Modules\Orders\Models\Order;
use Illuminate\Support\Facades\Gate;

class OrderTransitionController
{
    /**
     * Change an order's status
     *
     * Allowed moves are listed in the order's `available_transitions`:
     * starting work, cancelling (needs `orders.cancel` and a reason; fails
     * with 409 `order_has_payments` while payments are recorded) and
     * sending a ready order back to the workshop. An order becomes ready
     * through assembly (POST /assembly/{order}/complete).
     */
    public function __invoke(TransitionOrderRequest $request, Order $order, TransitionOrder $transition): OrderResource
    {
        $to = OrderStatus::from((string) $request->validated('to'));

        Gate::authorize($to === OrderStatus::Cancelled ? 'cancel' : 'update', $order);

        $order = $transition->handle($request->actor(), $order, $to, $request->validated('reason'));

        return new OrderResource($order->load(['branch', 'customer', 'creator', 'promoCode', 'lines.upcharges', 'lines.pieces.defects', 'lines.pieces.rackSlot', 'history.user', 'payments.user'])->loadCount('pieces'));
    }
}
