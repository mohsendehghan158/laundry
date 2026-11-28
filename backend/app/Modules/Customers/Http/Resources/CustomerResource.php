<?php

declare(strict_types=1);

namespace App\Modules\Customers\Http\Resources;

use App\Modules\Customers\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Customer
 */
class CustomerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'mobile' => $this->mobile,
            'phone' => $this->phone,
            'notes' => $this->notes,
            /** Shown as a warning whenever the customer is picked at the counter. */
            'alert' => $this->alert,
            /** @var array{type: 'individual'|'legal', name: string, national_id: string|null, economic_code: string|null, registration_number: string|null, postal_code: string|null, address: string|null, phone: string|null}|null Buyer details from the customer's last invoice. */
            'invoice_details' => $this->invoice_details,
            /** Present on a single customer and after saving. */
            'addresses' => CustomerAddressResource::collection($this->whenLoaded('addresses')),
            'created_at' => $this->created_at,
        ];
    }
}
