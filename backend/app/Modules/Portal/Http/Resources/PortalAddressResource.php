<?php

declare(strict_types=1);

namespace App\Modules\Portal\Http\Resources;

use App\Modules\Customers\Models\CustomerAddress;
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
        ];
    }
}
