<?php

declare(strict_types=1);

use App\Modules\B2B\Models\Company;
use App\Modules\Organization\Tenancy\OrganizationContext;

/** A hotel on account (credit 2,000,000) whose housekeeping orders for it. */
function hotel(object $shop, array $attributes = []): object
{
    return app(OrganizationContext::class)->runFor($shop->organization->id, function () use ($shop, $attributes): object {
        $company = Company::query()->create(['name' => 'هتل آزادی', 'credit_limit' => 2_000_000, ...$attributes]);
        $housekeeping = $company->departments()->create(['name' => 'خانه‌داری', 'code' => 'HK']);
        $restaurant = $company->departments()->create(['name' => 'رستوران']);
        $shop->customer->forceFill(['company_id' => $company->id])->save();

        return (object) ['company' => $company, 'housekeeping' => $housekeeping, 'restaurant' => $restaurant];
    });
}

function shirtPayload(object $shop, int $quantity = 1, array $extra = []): array
{
    return orderPayload($shop, ['lines' => [['item_id' => $shop->shirt->id, 'service_type_id' => $shop->dc->id, 'quantity' => $quantity]], ...$extra]);
}

function incomingNote(object $shop, object $hotel, array $overrides = []): array
{
    return [
        'type' => 'in',
        'company_id' => $hotel->company->id,
        'branch_id' => $shop->branch->id,
        'customer_id' => $shop->customer->id,
        'company_department_id' => $hotel->housekeeping->id,
        'lines' => [
            // The hotel says 20 shirts and 3 suits; 19 shirts are counted here.
            ['item_id' => $shop->shirt->id, 'service_type_id' => $shop->dc->id, 'expected_quantity' => 20, 'counted_quantity' => '۱۹'],
            ['item_id' => $shop->suit->id, 'service_type_id' => $shop->dc->id, 'expected_quantity' => 3, 'counted_quantity' => 3],
        ],
        ...$overrides,
    ];
}
