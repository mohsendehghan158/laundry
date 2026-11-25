<?php

declare(strict_types=1);

namespace App\Modules\Portal\Http\Controllers;

use App\Modules\Customers\Models\Customer;
use App\Modules\Orders\Models\Order;
use App\Modules\Portal\Http\Resources\PortalOrderResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CustomerOrderController
{
    /**
     * My orders
     *
     * Newest first, the last 100.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $orders = Order::query()
            ->with('branch')
            ->withCount('pieces')
            ->where('customer_id', $this->customer($request)->id)
            ->latest('id')
            ->limit(100)
            ->get();

        return PortalOrderResource::collection($orders);
    }

    /**
     * One of my orders
     *
     * With its lines, status timeline and payments.
     */
    public function show(Request $request, string $publicId): PortalOrderResource
    {
        $order = Order::query()
            ->with(['branch', 'lines', 'history', 'payments'])
            ->withCount('pieces')
            ->where('customer_id', $this->customer($request)->id)
            ->where('public_id', $publicId)
            ->firstOrFail();

        return new PortalOrderResource($order);
    }

    private function customer(Request $request): Customer
    {
        /** @var Customer */
        return $request->user('customer');
    }
}
