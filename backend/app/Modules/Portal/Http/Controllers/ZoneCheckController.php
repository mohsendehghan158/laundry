<?php

declare(strict_types=1);

namespace App\Modules\Portal\Http\Controllers;

use App\Modules\Logistics\Support\ZoneLocator;
use App\Modules\Portal\Http\Resources\PortalZoneResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ZoneCheckController
{
    /**
     * Check a point for pickup and delivery
     *
     * Whether the business picks up and delivers at a point on the map,
     * and what delivery costs there.
     *
     * @unauthenticated
     *
     * @response array{data: array{served: bool, zone: array{id: int, name: string, branch_name: string|null, delivery_fee: int, free_delivery_over: int|null, min_order: int|null}|null}}
     */
    public function __invoke(Request $request, ZoneLocator $locator): JsonResponse
    {
        /** @var array{latitude: float|string, longitude: float|string} $point */
        $point = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $zone = $locator->find($point['latitude'], $point['longitude']);

        return response()->json(['data' => [
            'served' => $zone !== null,
            'zone' => $zone === null ? null : (new PortalZoneResource($zone))->resolve($request),
        ]]);
    }
}
