<?php

declare(strict_types=1);

namespace App\Modules\Portal\Http\Controllers;

use App\Modules\Customers\Models\Customer;
use App\Modules\Orders\Models\Order;
use App\Modules\Portal\Actions\CancelOnlineOrder;
use App\Modules\Portal\Actions\PlaceOnlineOrder;
use App\Modules\Portal\Http\Requests\OnlineOrderRequest;
use App\Modules\Portal\Http\Resources\PortalOrderResource;
use App\Modules\Portal\Http\Resources\PortalZoneResource;
use App\Modules\Portal\Support\OnlineOrderQuote;
use Illuminate\Http\JsonResponse;
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

    /**
     * Price an online order
     *
     * What the order would cost before it is placed: the items at the
     * shop's prices, the zone's delivery fee and minimum order. Fails with
     * 422 `address_not_served` or `address_without_point`.
     *
     * @response array{data: array{zone: array{id: int, name: string, branch_name: string, delivery_fee: int, free_delivery_over: int|null, min_order: int|null}, items_by_driver: bool, lines: list<array{item_name: string, service_name: string, quantity: string, line_total: int}>, subtotal: int, discount_amount: int, tax_amount: int, items_total: int, delivery_fee: int, total: int, min_order: int|null, below_minimum: bool}}
     */
    public function quote(OnlineOrderRequest $request, OnlineOrderQuote $quotes): JsonResponse
    {
        /** @var array{address_id: int, items_by_driver?: bool, lines?: list<array<string, mixed>>} $input */
        $input = $request->validated();
        $quote = $quotes->for($this->customer($request), $input);
        $priced = $quote['priced'];

        return response()->json(['data' => [
            'zone' => (new PortalZoneResource($quote['zone']))->resolve($request),
            'items_by_driver' => $quote['items_by_driver'],
            'lines' => array_map(fn (array $line): array => [
                'item_name' => $line['item_name'],
                'service_name' => $line['service_name'],
                'quantity' => $line['quantity'],
                'line_total' => $line['line_total'],
            ], $priced['lines']),
            'subtotal' => $priced['subtotal'],
            'discount_amount' => $priced['discount_amount'],
            'tax_amount' => $priced['tax_amount'],
            'items_total' => $quote['items_total'],
            'delivery_fee' => $quote['delivery_fee'],
            'total' => $quote['total'],
            'min_order' => $quote['min_order'],
            'below_minimum' => $quote['below_minimum'],
        ]]);
    }

    /**
     * Place an online order
     *
     * Books a place in the pickup window and creates the order as
     * "pending pickup" at the branch serving the address; the customer is
     * texted a confirmation. Send an `Idempotency-Key` header to make
     * retries safe: a repeated key returns the first order with 200.
     * Fails with 409 `slot_unavailable` or `slot_full` when the window
     * cannot be booked, and 422 `order_below_minimum` under the zone's
     * minimum order.
     *
     * @status 201
     */
    public function store(OnlineOrderRequest $request, PlaceOnlineOrder $place): JsonResponse
    {
        /** @var array{address_id: int, slot_id: int, items_by_driver?: bool, lines?: list<array<string, mixed>>, notes?: string|null} $input */
        $input = $request->validated();
        $key = $request->header('Idempotency-Key');
        [$order, $created] = $place->handle($this->customer($request), $input, is_string($key) && $key !== '' ? mb_substr($key, 0, 60) : null);

        return (new PortalOrderResource($this->detailed($order)))->response()->setStatusCode($created ? 201 : 200);
    }

    /**
     * Cancel my online order
     *
     * Only while it waits for pickup; the window's place is freed. Fails
     * with 409 `order_not_cancellable` afterwards.
     */
    public function cancel(Request $request, string $publicId, CancelOnlineOrder $cancel): PortalOrderResource
    {
        $order = Order::query()
            ->where('customer_id', $this->customer($request)->id)
            ->where('public_id', $publicId)
            ->firstOrFail();

        return new PortalOrderResource($this->detailed($cancel->handle($order)));
    }

    private function detailed(Order $order): Order
    {
        return $order->load(['branch', 'lines', 'history', 'payments'])->loadCount('pieces');
    }

    private function customer(Request $request): Customer
    {
        /** @var Customer */
        return $request->user('customer');
    }
}
