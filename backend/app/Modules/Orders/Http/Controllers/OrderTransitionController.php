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
     * Allowed moves are listed in the order's `available_transitions`.
     * Marking it `ready` marks its pieces ready and can record where it is
     * kept (`rack_location`); cancelling needs `orders.cancel` and a reason,
     * and fails with 409 `order_has_payments` while payments are recorded.
     */
    public function __invoke(TransitionOrderRequest $request, Order $order, TransitionOrder $transition): OrderResource
    {
        $to = OrderStatus::from((string) $request->validated('to'));

        Gate::authorize($to === OrderStatus::Cancelled ? 'cancel' : 'update', $order);

        $order = $transition->handle($request->actor(), $order, $to, $request->validated('reason'), $request->validated('rack_location'));

        return new OrderResource($order->load(['branch', 'customer', 'creator', 'lines.upcharges', 'lines.pieces.defects', 'history.user'])->loadCount('pieces'));
    }
}
