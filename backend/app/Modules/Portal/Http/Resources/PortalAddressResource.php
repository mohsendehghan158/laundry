<?php

declare(strict_types=1);

namespace App\Modules\Portal\Http\Resources;

use App\Modules\Customers\Models\CustomerAddress;
use App\Modules\Logistics\Support\ZoneLocator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CustomerAddress
 */
class PortalAddressResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'address' => $this->address,
            'latitude' => $this->latitude === null ? null : (float) $this->latitude,
            'longitude' => $this->longitude === null ? null : (float) $this->longitude,
            'is_default' => (bool) $this->is_default,
            /** @var array{id: int, name: string, branch_name: string|null, delivery_fee: int, free_delivery_over: int|null, min_order: int|null}|null The service zone the point is in; null = no pickup or delivery there. */
            'zone' => ($zone = app(ZoneLocator::class)->find($this->latitude, $this->longitude)) === null
                ? null
                : (new PortalZoneResource($zone))->resolve($request),
        ];
    }
}
