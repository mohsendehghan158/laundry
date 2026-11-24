<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Catalog\Actions\CreateDefaultCatalog;
use App\Modules\Catalog\Enums\PriceUnit;
use App\Modules\Catalog\Models\Item;
use App\Modules\Catalog\Models\ItemCategory;
use App\Modules\Catalog\Models\Price;
use App\Modules\Catalog\Models\PriceList;
use App\Modules\Catalog\Models\ServiceType;
use App\Modules\Organization\Tenancy\OrganizationContext;

/**
 * A believable counter catalog for the demo organization. Prices are in
 * rials (the panel shows them in toman by default).
 */
final class DemoCatalogSeeder
{
    /**
     * category => [icon, [[item, unit, pieces per unit, [service code => price in toman]]]]
     *
     * @var array<string, array{0: string, 1: list<array{0: string, 1: PriceUnit, 2: int, 3: array<string, int>}>}>
     */
    private const array CATALOG = [
        'لباس روزمره' => ['👕', [
            ['پیراهن', PriceUnit::Piece, 1, ['DC' => 90_000, 'WS' => 60_000, 'PR' => 40_000]],
            ['شلوار', PriceUnit::Piece, 1, ['DC' => 110_000, 'WS' => 70_000, 'PR' => 50_000]],
            ['تی‌شرت', PriceUnit::Piece, 1, ['DC' => 70_000, 'WS' => 45_000, 'PR' => 30_000]],
            ['دامن', PriceUnit::Piece, 1, ['DC' => 110_000, 'PR' => 50_000]],
            ['ژاکت و بافت', PriceUnit::Piece, 1, ['DC' => 150_000]],
        ]],
        'کت و رسمی' => ['👔', [
            ['کت', PriceUnit::Piece, 1, ['DC' => 220_000, 'PR' => 90_000]],
            ['کت‌وشلوار', PriceUnit::Piece, 2, ['DC' => 320_000, 'PR' => 130_000]],
            ['کراوات', PriceUnit::Piece, 1, ['DC' => 60_000]],
            ['لباس مجلسی', PriceUnit::Piece, 1, ['DC' => 450_000]],
            ['لباس عروس', PriceUnit::Piece, 1, ['DC' => 2_500_000]],
        ]],
        'پالتو و کاپشن' => ['🧥', [
            ['پالتو', PriceUnit::Piece, 1, ['DC' => 380_000]],
            ['کاپشن', PriceUnit::Piece, 1, ['DC' => 320_000, 'WS' => 250_000]],
            ['بارانی', PriceUnit::Piece, 1, ['DC' => 300_000]],
        ]],
        'منسوجات خانگی' => ['🛏️', [
            ['پتو یک‌نفره', PriceUnit::Piece, 1, ['DC' => 350_000, 'WS' => 280_000]],
            ['پتو دونفره', PriceUnit::Piece, 1, ['DC' => 480_000, 'WS' => 380_000]],
            ['روتختی', PriceUnit::Piece, 1, ['DC' => 300_000, 'WS' => 220_000]],
            ['پرده', PriceUnit::SquareMetre, 1, ['DC' => 120_000, 'WS' => 90_000]],
            ['شست‌وشوی کیلویی', PriceUnit::Kilogram, 1, ['WS' => 80_000]],
        ]],
        'فرش' => ['🧶', [
            ['فرش ماشینی', PriceUnit::SquareMetre, 1, ['WS' => 150_000]],
            ['فرش دست‌باف', PriceUnit::SquareMetre, 1, ['WS' => 320_000]],
        ]],
    ];

    public function __construct(
        private readonly CreateDefaultCatalog $createDefaultCatalog,
        private readonly OrganizationContext $context,
    ) {}

    public function run(int $organizationId): void
    {
        $this->createDefaultCatalog->handle($organizationId);

        $this->context->runFor($organizationId, function (): void {
            $services = ServiceType::query()->pluck('id', 'code');
            $list = PriceList::default();
            $categoryOrder = 0;

            foreach (self::CATALOG as $categoryName => [$icon, $items]) {
                $category = ItemCategory::query()->create(['name' => $categoryName, 'icon' => $icon, 'sort_order' => $categoryOrder++]);

                foreach ($items as $itemOrder => [$name, $unit, $pieces, $prices]) {
                    $item = Item::query()->create([
                        'item_category_id' => $category->id,
                        'name' => $name,
                        'unit' => $unit,
                        'pieces_per_unit' => $pieces,
                        'sort_order' => $itemOrder,
                    ]);

                    foreach ($prices as $code => $toman) {
                        Price::query()->create([
                            'price_list_id' => $list->id,
                            'item_id' => $item->id,
                            'service_type_id' => $services[$code],
                            'price' => $toman * 10,
                        ]);
                    }
                }
            }
        });
    }
}
