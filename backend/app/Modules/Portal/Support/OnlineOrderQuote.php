<?php

declare(strict_types=1);

namespace App\Modules\Portal\Support;

use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Models\CustomerAddress;
use App\Modules\Logistics\Models\Zone;
use App\Modules\Logistics\Support\ZoneLocator;
use App\Modules\Orders\Services\OrderPricer;
use App\Support\Exceptions\ApiException;

/**
 * What an online order would cost: the items at the shop's prices, plus
 * the delivery fee of the zone the pickup address is in. Fails with 422
 * `address_without_point` or `address_not_served` when the address
 * cannot be served.
 *
 * When the customer leaves the items for the driver to list, there are
 * no lines yet: the fee is the zone's full fee, and the minimum order is
 * checked once the driver has listed them.
 *
 * @phpstan-import-type Priced from OrderPricer
 *
 * @phpstan-type Quote array{address: CustomerAddress, zone: Zone, items_by_driver: bool, priced: Priced, items_total: int, delivery_fee: int, total: int, min_order: int|null, below_minimum: bool}
 */
final readonly class OnlineOrderQuote
{
    public function __construct(private ZoneLocator $zones, private OrderPricer $pricer) {}

    /**
     * @param  array{address_id: int, items_by_driver?: bool, lines?: list<array<string, mixed>>}  $input
     * @return Quote
     */
    public function for(Customer $customer, array $input): array
    {
        $address = $customer->addresses()->findOrFail($input['address_id']);

        if ($address->latitude === null || $address->longitude === null) {
            throw new ApiException('address_without_point', __('api.errors.address_without_point'), 422);
        }

        $zone = $this->zones->find($address->latitude, $address->longitude);

        if ($zone === null) {
            throw new ApiException('address_not_served', __('api.errors.address_not_served'), 422);
        }

        $byDriver = (bool) ($input['items_by_driver'] ?? false);
        $priced = $this->pricer->price(['lines' => $byDriver ? [] : ($input['lines'] ?? []), 'customer_id' => $customer->id]);
        $itemsTotal = $priced['total'];
        $fee = $zone->deliveryFeeFor($itemsTotal);

        return [
            'address' => $address,
            'zone' => $zone,
            'items_by_driver' => $byDriver,
            'priced' => $priced,
            'items_total' => $itemsTotal,
            'delivery_fee' => $fee,
            'total' => $itemsTotal + $fee,
            'min_order' => $zone->min_order,
            'below_minimum' => ! $byDriver && $zone->min_order !== null && $itemsTotal < $zone->min_order,
        ];
    }
}
