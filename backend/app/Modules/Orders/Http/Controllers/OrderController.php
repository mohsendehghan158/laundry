<?php

declare(strict_types=1);

namespace App\Modules\Orders\Http\Controllers;

use App\Modules\Identity\Models\User;
use App\Modules\Orders\Actions\CreateOrder;
use App\Modules\Orders\Actions\UpdateOrder;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Http\Requests\StoreOrderRequest;
use App\Modules\Orders\Http\Requests\UpdateOrderRequest;
use App\Modules\Orders\Http\Resources\OrderResource;
use App\Modules\Orders\Models\Order;
use App\Support\Http\PerPage;
use App\Support\Text\Digits;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class OrderController
{
    /** Relations an order is shown with. */
    private const array DETAIL = ['branch', 'customer', 'creator', 'lines.upcharges', 'lines.pieces.defects', 'history.user'];

    /**
     * List orders
     *
     * Orders of the branches the user can work in, newest first.
     */
    #[QueryParameter('filter[q]', 'Ticket number, or part of the customer\'s name or mobile.', type: 'string')]
    #[QueryParameter('filter[status]', 'One or more statuses, comma separated.', type: 'string')]
    #[QueryParameter('filter[branch_id]', type: 'integer')]
    #[QueryParameter('filter[customer_id]', type: 'integer')]
    #[QueryParameter('filter[due]', '"today": open orders due today; "overdue": open orders past their due time.', type: 'string')]
    #[QueryParameter('sort', 'One of created_at, due_at, ticket_number; prefix with "-" for descending.', type: 'string', default: '-created_at')]
    #[QueryParameter('page', type: 'integer', default: 1)]
    #[QueryParameter('per_page', 'Page size, 1-100.', type: 'integer', default: 25)]
    public function index(Request $request, #[CurrentUser] User $actor): AnonymousResourceCollection
    {
        Gate::authorize('orders.view');

        $orders = QueryBuilder::for(Order::query()->visibleTo($actor)->with(['branch', 'customer'])->withCount('pieces'))
            ->allowedFilters(
                AllowedFilter::callback('q', function (Builder $query, mixed $value): void {
                    $term = trim(Digits::toLatin(is_array($value) ? implode(',', $value) : (string) $value));
                    $query->where(fn (Builder $query) => $query
                        ->whereLike('ticket_number', '%'.mb_strtoupper($term).'%')
                        ->orWhereHas('customer', fn (Builder $customers) => $customers->search($term)));
                }),
                AllowedFilter::callback('status', fn (Builder $query, mixed $value) => $query->whereIn('status', (array) $value)),
                AllowedFilter::exact('branch_id'),
                AllowedFilter::exact('customer_id'),
                AllowedFilter::callback('due', function (Builder $query, mixed $value): void {
                    $open = array_map(fn (OrderStatus $status): string => $status->value, array_filter(OrderStatus::cases(), fn (OrderStatus $status): bool => $status->isOpen()));
                    $query->whereIn('status', $open);

                    match ($value) {
                        'today' => $query->whereBetween('due_at', [now()->startOfDay(), now()->endOfDay()]),
                        'overdue' => $query->where('due_at', '<', now()),
                        default => null,
                    };
                }),
            )
            ->allowedSorts('created_at', 'due_at', AllowedSort::field('ticket_number'))
            ->defaultSort('-created_at')
            ->paginate(PerPage::from($request))
            ->withQueryString();

        return OrderResource::collection($orders);
    }

    /**
     * Take in an order
     *
     * Prices the lines against the catalog, gives the order the branch's
     * next ticket number, computes the due time from the turnaround tier
     * and working hours, and tags every piece. Send an `Idempotency-Key`
     * header so that a retried request returns the same order (200)
     * instead of creating a second one (201).
     */
    public function store(StoreOrderRequest $request, CreateOrder $createOrder): JsonResponse
    {
        [$order, $created] = $createOrder->handle($request->actor(), $request->validated(), $request->idempotencyKey());

        return (new OrderResource($order->load(self::DETAIL)->loadCount('pieces')))
            ->response()
            ->setStatusCode($created ? 201 : 200);
    }

    /**
     * Show an order
     */
    public function show(Order $order): OrderResource
    {
        Gate::authorize('view', $order);

        return new OrderResource($order->load(self::DETAIL)->loadCount('pieces'));
    }

    /**
     * Change an order's lines
     *
     * Only before any work has started (status `received`). Fails with 409
     * `order_version_conflict` when the order changed since `version`, and
     * `order_not_editable` once it is in process. Pieces are tagged again.
     */
    public function update(UpdateOrderRequest $request, Order $order, UpdateOrder $updateOrder): OrderResource
    {
        Gate::authorize('update', $order);

        $order = $updateOrder->handle($request->actor(), $order, $request->validated());

        return new OrderResource($order->load(self::DETAIL)->loadCount('pieces'));
    }
}
