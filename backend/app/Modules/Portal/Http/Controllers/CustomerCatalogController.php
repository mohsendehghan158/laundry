<?php

declare(strict_types=1);

namespace App\Modules\Portal\Http\Controllers;

use App\Modules\Catalog\Models\ItemCategory;
use App\Modules\Catalog\Models\Price;
use App\Modules\Catalog\Models\PriceList;
use App\Modules\Catalog\Models\ServiceType;
use Illuminate\Http\JsonResponse;

class CustomerCatalogController
{
    /**
     * What I can order
     *
     * Active items by category, each with the services it has a price for
     * (whole rials per unit, before tax). Items with no price are left out.
     *
     * @response array{data: array{categories: list<array{id: int, name: string, items: list<array{id: int, name: string, unit: 'piece'|'kg'|'sqm', prices: list<array{service_type_id: int, service_name: string, price: int}>}>}>}}
     */
    public function __invoke(): JsonResponse
    {
        $services = ServiceType::query()->active()->ordered()->get()->keyBy('id');
        $prices = Price::query()
            ->where('price_list_id', PriceList::default()->id)
            ->whereIn('service_type_id', $services->keys())
            ->get()
            ->groupBy('item_id');

        $categories = [];
        $query = ItemCategory::query()->active()->ordered()->with(['items' => fn ($query) => $query->active()->ordered()]);

        foreach ($query->get() as $category) {
            $items = [];
            foreach ($category->items as $item) {
                $itemPrices = [];
                foreach ($services as $service) {
                    $price = ($prices[$item->id] ?? collect())->firstWhere('service_type_id', $service->id);
                    if ($price instanceof Price) {
                        $itemPrices[] = ['service_type_id' => $service->id, 'service_name' => $service->name, 'price' => $price->price];
                    }
                }
                if ($itemPrices !== []) {
                    $items[] = ['id' => $item->id, 'name' => $item->name, 'unit' => $item->unit->value, 'prices' => $itemPrices];
                }
            }
            if ($items !== []) {
                $categories[] = ['id' => $category->id, 'name' => $category->name, 'items' => $items];
            }
        }

        return response()->json(['data' => ['categories' => $categories]]);
    }
}
