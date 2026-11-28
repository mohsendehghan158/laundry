<?php

declare(strict_types=1);

namespace App\Modules\B2B\Http\Resources;

use App\Modules\B2B\Models\Company;
use App\Modules\B2B\Models\CompanyDepartment;
use App\Modules\Customers\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A company and its contract. Amounts are whole rials.
 *
 * @mixin Company
 */
class CompanyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'national_id' => $this->national_id,
            'economic_code' => $this->economic_code,
            'registration_number' => $this->registration_number,
            'postal_code' => $this->postal_code,
            'address' => $this->address,
            'phone' => $this->phone,
            'contact_name' => $this->contact_name,
            'contact_mobile' => $this->contact_mobile,
            'contract_number' => $this->contract_number,
            'contract_starts_on' => $this->contract_starts_on?->format('Y-m-d'),
            'contract_ends_on' => $this->contract_ends_on?->format('Y-m-d'),
            /** The contract's dates include today. */
            'contract_running' => $this->contractRunning(),
            /** @var array{id: int, name: string}|null */
            'price_list' => $this->whenLoaded('priceList', fn (): ?array => $this->priceList === null ? null : ['id' => $this->priceList->id, 'name' => $this->priceList->name]),
            /** Empty: the company pays like any customer. */
            'credit_limit' => $this->credit_limit,
            'payment_terms_days' => $this->payment_terms_days,
            /** Orders are taken on account, up to the credit limit. */
            'on_account' => $this->onAccount(),
            /** What it owes on orders not yet paid in full. */
            'outstanding' => $this->whenHas('outstanding', fn (): int => (int) $this->getAttribute('outstanding')),
            'notes' => $this->notes,
            'is_active' => $this->is_active,
            /** @var list<array{id: int, name: string, code: string|null, is_active: bool}> */
            'departments' => $this->whenLoaded('departments', fn (): array => $this->departments->map(fn (CompanyDepartment $department): array => [
                'id' => $department->id,
                'name' => $department->name,
                'code' => $department->code,
                'is_active' => $department->is_active,
            ])->all()),
            /** @var list<array{id: int, name: string, mobile: string}> Customers who order for the company. */
            'members' => $this->whenLoaded('members', fn (): array => $this->members->map(fn (Customer $customer): array => [
                'id' => $customer->id,
                'name' => $customer->name,
                'mobile' => $customer->mobile,
            ])->all()),
        ];
    }
}
