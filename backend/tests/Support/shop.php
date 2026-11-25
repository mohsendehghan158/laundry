<?php

declare(strict_types=1);

use App\Modules\Catalog\Enums\PriceUnit;
use App\Modules\Catalog\Enums\UpchargeType;
use App\Modules\Catalog\Models\Color;
use App\Modules\Catalog\Models\DefectType;
use App\Modules\Catalog\Models\Item;
use App\Modules\Catalog\Models\ItemCategory;
use App\Modules\Catalog\Models\Price;
use App\Modules\Catalog\Models\PriceList;
use App\Modules\Catalog\Models\ServiceType;
use App\Modules\Catalog\Models\TurnaroundTier;
use App\Modules\Catalog\Models\Upcharge;
use App\Modules\Customers\Models\Customer;
use App\Modules\Identity\Models\User;
use App\Modules\Organization\Enums\BranchType;
use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\Organization;
use App\Modules\Organization\Tenancy\OrganizationContext;

/**
 * A small shop: one counter branch, a shirt (DC 900,000), a suit that
 * makes two pieces (DC 3,200,000), washing by weight (WS 800,000 / kg),
 * a 30% silk upcharge, a 72h default tier and a 24h +50% express tier.
 */
function shop(): object
{
    $organization = Organization::factory()->create();
    $branch = Branch::factory()->for($organization)->create(['code' => 'T1', 'type' => BranchType::Store]);

    return app(OrganizationContext::class)->runFor($organization->id, function () use ($organization, $branch): object {
        $dc = ServiceType::factory()->for($organization)->create(['name' => 'خشکشویی', 'code' => 'DC']);
        $ws = ServiceType::factory()->for($organization)->create(['name' => 'شست‌وشو', 'code' => 'WS']);
        $category = ItemCategory::factory()->for($organization)->create();
        $shirt = Item::factory()->for($category, 'category')->create(['name' => 'پیراهن']);
        $suit = Item::factory()->for($category, 'category')->create(['name' => 'کت‌وشلوار', 'pieces_per_unit' => 2]);
        $laundry = Item::factory()->for($category, 'category')->create(['name' => 'کیلویی', 'unit' => PriceUnit::Kilogram]);
        $list = PriceList::default();

        foreach ([[$shirt, $dc, 900_000], [$suit, $dc, 3_200_000], [$laundry, $ws, 800_000]] as [$item, $service, $price]) {
            Price::query()->create(['price_list_id' => $list->id, 'item_id' => $item->id, 'service_type_id' => $service->id, 'price' => $price]);
        }

        return (object) [
            'organization' => $organization,
            'branch' => $branch,
            'dc' => $dc,
            'ws' => $ws,
            'shirt' => $shirt,
            'suit' => $suit,
            'laundry' => $laundry,
            'silk' => Upcharge::factory()->for($organization)->create(['name' => 'ابریشم', 'type' => UpchargeType::Percent, 'value' => 30]),
            'normal' => TurnaroundTier::factory()->for($organization)->create(['name' => 'عادی', 'duration_hours' => 72, 'is_default' => true]),
            'express' => TurnaroundTier::factory()->for($organization)->create(['name' => 'فوری', 'duration_hours' => 24, 'surcharge_percent' => 50]),
            'red' => Color::factory()->for($organization)->create(['name' => 'قرمز']),
            'tear' => DefectType::factory()->for($organization)->create(['name' => 'پارگی']),
            'customer' => Customer::factory()->for($organization)->create(['name' => 'مشتری نمونه', 'mobile' => '09121112233']),
        ];
    });
}

function staff(object $shop, string $role = 'cashier'): User
{
    return test()->userWithRole($role, $shop->organization, [$shop->branch]);
}

function orderPayload(object $shop, array $overrides = []): array
{
    return [
        'branch_id' => $shop->branch->id,
        'customer_id' => $shop->customer->id,
        'lines' => [
            ['item_id' => $shop->shirt->id, 'service_type_id' => $shop->dc->id, 'quantity' => 3, 'upcharge_ids' => [$shop->silk->id],
                'pieces' => [['color_id' => $shop->red->id, 'brand' => 'Zara', 'defect_type_ids' => [$shop->tear->id], 'notes' => 'یقه']]],
            ['item_id' => $shop->suit->id, 'service_type_id' => $shop->dc->id, 'quantity' => 1],
            ['item_id' => $shop->laundry->id, 'service_type_id' => $shop->ws->id, 'quantity' => '۲٫۵'],
        ],
        ...$overrides,
    ];
}
