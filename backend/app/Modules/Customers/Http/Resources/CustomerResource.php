<?php

declare(strict_types=1);

namespace App\Modules\Customers\Http\Resources;

use App\Modules\B2B\Models\CompanyDepartment;
use App\Modules\B2B\Support\CompanyAccounts;
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
            /** @var array{id: int, name: string, on_account: bool, credit_available: int|null, departments?: list<array{id: int, name: string}>}|null The company the customer orders for; on a single customer with credit left and active departments. */
            'company' => $this->whenLoaded('company', fn (): ?array => $this->company === null || ! $this->company->is_active ? null : [
                'id' => $this->company->id,
                'name' => $this->company->name,
                'on_account' => $this->company->onAccount(),
                'credit_available' => $this->company->relationLoaded('departments') ? app(CompanyAccounts::class)->available($this->company) : null,
                ...($this->company->relationLoaded('departments') ? ['departments' => $this->company->departments
                    ->filter(fn (CompanyDepartment $department): bool => $department->is_active)
                    ->map(fn (CompanyDepartment $department): array => ['id' => $department->id, 'name' => $department->name])
                    ->values()->all()] : []),
            ]),
            /** Present on a single customer and after saving. */
            'addresses' => CustomerAddressResource::collection($this->whenLoaded('addresses')),
            'created_at' => $this->created_at,
        ];
    }
}
