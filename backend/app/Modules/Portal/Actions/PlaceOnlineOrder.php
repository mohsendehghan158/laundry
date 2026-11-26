<?php

declare(strict_types=1);

namespace App\Modules\Portal\Actions;

use App\Modules\Customers\Models\Customer;
use App\Modules\Logistics\Actions\ReserveSlot;
use App\Modules\Logistics\Models\Slot;
use App\Modules\Logistics\Support\LogisticsSettings;
use App\Modules\Logistics\Support\SlotAvailability;
use App\Modules\Orders\Enums\OrderSource;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Events\OrderStatusChanged;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Services\OrderPricer;
use App\Modules\Orders\Support\OrderWriter;
use App\Modules\Organization\Support\Sequences;
use App\Modules\Portal\Support\OnlineOrderQuote;
use App\Support\Exceptions\ApiException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * A customer orders a pickup in the portal: the order waits for the
 * driver ("pending pickup") in the booked window, at the branch serving
 * the address. Its pieces are tagged when they reach the shop.
 */
final readonly class PlaceOnlineOrder
{
    public function __construct(
        private OnlineOrderQuote $quotes,
        private SlotAvailability $availability,
        private ReserveSlot $slots,
        private OrderPricer $pricer,
        private OrderWriter $writer,
        private Sequences $sequences,
        private LogisticsSettings $settings,
    ) {}

    /**
     * With an idempotency key, a repeated request returns the order made
     * the first time; the second element tells whether it was just created.
     *
     * @param  array{address_id: int, slot_id: int, items_by_driver?: bool, lines?: list<array<string, mixed>>, notes?: string|null}  $input
     * @return array{0: Order, 1: bool}
     */
    public function handle(Customer $customer, array $input, ?string $idempotencyKey = null): array
    {
        // Keys are per customer, so one customer's key can never reach another's order.
        $key = $idempotencyKey === null ? null : "portal:{$customer->id}:{$idempotencyKey}";

        if ($key !== null && ($existing = $this->existing($customer, $key))) {
            return [$existing, false];
        }

        $quote = $this->quotes->for($customer, $input);

        if ($quote['below_minimum']) {
            throw new ApiException('order_below_minimum', __('api.errors.order_below_minimum'), 422);
        }

        $zone = $quote['zone'];
        $slot = Slot::query()->where('branch_id', $zone->branch_id)->find($input['slot_id']);

        if ($slot === null || ! $this->availability->bookable($slot)) {
            throw ApiException::conflict('slot_unavailable');
        }

        $timezone = $this->settings->timezone();
        $startsAt = $slot->startsAt($timezone);
        $priced = $quote['priced'];
        $branch = $zone->branch;

        try {
            $order = DB::transaction(function () use ($customer, $input, $key, $quote, $slot, $startsAt, $timezone, $priced, $branch): Order {
                // Fails with slot_full (and rolls everything back) if the last place just went.
                $this->slots->handle($slot);

                $number = $this->sequences->next($branch->organization_id, "ticket:{$branch->id}");

                $order = Order::query()->create([
                    'branch_id' => $branch->id,
                    'customer_id' => $customer->id,
                    'ticket_number' => $branch->code.'-'.str_pad((string) $number, 5, '0', STR_PAD_LEFT),
                    'status' => OrderStatus::PendingPickup,
                    'payment_status' => PaymentStatus::for($quote['total'], 0),
                    'source' => OrderSource::Online,
                    'received_at' => null,
                    // A first estimate, from the start of the pickup window.
                    'due_at' => $this->pricer->dueAt($startsAt, $priced['duration_hours']),
                    'pickup_slot_id' => $slot->id,
                    // Stored in UTC like every timestamp; the slot's times are local.
                    'pickup_starts_at' => $startsAt->utc(),
                    'pickup_ends_at' => $slot->endsAt($timezone)->utc(),
                    'pickup_address' => $quote['address']->address,
                    'pickup_latitude' => $quote['address']->latitude,
                    'pickup_longitude' => $quote['address']->longitude,
                    'items_by_driver' => $quote['items_by_driver'],
                    'notes' => $input['notes'] ?? null,
                    'idempotency_key' => $key,
                    'created_by' => null,
                    'paid_amount' => 0,
                    'version' => 1,
                    ...$this->writer->attributes($priced, null),
                    'delivery_fee' => $quote['delivery_fee'],
                    'total' => $quote['total'],
                ]);

                $this->writer->writeLines($order, $priced, tagPieces: false);

                $order->history()->create(['from_status' => null, 'to_status' => OrderStatus::PendingPickup, 'user_id' => null]);
                OrderStatusChanged::dispatch($order, null, OrderStatus::PendingPickup);

                return $order;
            });
        } catch (UniqueConstraintViolationException $e) {
            // Two identical retries raced; the other one won.
            if ($key !== null && ($existing = $this->existing($customer, $key))) {
                return [$existing, false];
            }

            throw $e;
        }

        return [$order, true];
    }

    private function existing(Customer $customer, string $key): ?Order
    {
        return Order::query()->where('customer_id', $customer->id)->where('idempotency_key', $key)->first();
    }
}
