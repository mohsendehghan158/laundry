<?php

declare(strict_types=1);

namespace App\Modules\Portal\Http\Controllers;

use App\Modules\Customers\Models\Customer;
use App\Modules\Logistics\Support\SlotAvailability;
use App\Modules\Logistics\Support\ZoneLocator;
use App\Modules\Portal\Http\Resources\PortalSlotResource;
use App\Modules\Portal\Http\Resources\PortalZoneResource;
use App\Support\Exceptions\ApiException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerSlotController
{
    /**
     * Pickup windows for an address
     *
     * The windows the customer can book for one of their addresses: those
     * of the branch serving its zone, with room left, from the lead time
     * on. Fails with 422 `address_not_served` outside every zone.
     *
     * @response array{data: array{zone: array{id: int, name: string, branch_name: string|null, delivery_fee: int, free_delivery_over: int|null, min_order: int|null}, slots: list<array{id: int, date: string, starts_at: string, ends_at: string, remaining: int}>}}
     */
    public function __invoke(Request $request, ZoneLocator $locator, SlotAvailability $availability): JsonResponse
    {
        $request->validate(['address_id' => ['required', 'integer']]);

        /** @var Customer $customer */
        $customer = $request->user('customer');
        $address = $customer->addresses()->findOrFail($request->integer('address_id'));

        if ($address->latitude === null || $address->longitude === null) {
            throw new ApiException('address_without_point', __('api.errors.address_without_point'), 422);
        }

        $zone = $locator->find($address->latitude, $address->longitude);
        if ($zone === null) {
            throw new ApiException('address_not_served', __('api.errors.address_not_served'), 422);
        }

        return response()->json(['data' => [
            'zone' => (new PortalZoneResource($zone))->resolve($request),
            'slots' => PortalSlotResource::collection($availability->forBranch($zone->branch_id))->resolve($request),
        ]]);
    }
}
