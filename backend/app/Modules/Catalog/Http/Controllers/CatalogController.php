<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Http\Resources\ColorResource;
use App\Modules\Catalog\Http\Resources\DefectTypeResource;
use App\Modules\Catalog\Http\Resources\ItemCategoryResource;
use App\Modules\Catalog\Http\Resources\ServiceTypeResource;
use App\Modules\Catalog\Http\Resources\TurnaroundTierResource;
use App\Modules\Catalog\Http\Resources\UpchargeResource;
use App\Modules\Catalog\Models\Color;
use App\Modules\Catalog\Models\DefectType;
use App\Modules\Catalog\Models\ItemCategory;
use App\Modules\Catalog\Models\Price;
use App\Modules\Catalog\Models\PriceList;
use App\Modules\Catalog\Models\ServiceType;
use App\Modules\Catalog\Models\TurnaroundTier;
use App\Modules\Catalog\Models\Upcharge;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CatalogController
{
    /**
     * Show the counter catalog
     *
     * Everything the counter needs to take an order, in one response:
     * active services, categories with their active items, the price
     * matrix, upcharges, turnaround tiers, defects and colors. Prices are
     * whole rials per unit, keyed by item and service.
     *
     * @response array{data: array{service_types: list<ServiceTypeResource>, categories: list<ItemCategoryResource>, prices: list<array{item_id: int, service_type_id: int, price: int}>, upcharges: list<UpchargeResource>, turnaround_tiers: list<TurnaroundTierResource>, defect_types: list<DefectTypeResource>, colors: list<ColorResource>}}
     */
    public function __invoke(Request $request): JsonResponse
    {
        Gate::authorize('catalog.view');

        $serviceTypes = ServiceType::query()->active()->ordered()->get();
        $categories = ItemCategory::query()
            ->active()
            ->ordered()
            ->with(['items' => fn ($query) => $query->active()->ordered()])
            ->get()
            ->filter(fn (ItemCategory $category): bool => $category->items->isNotEmpty())
            ->values();
        $itemIds = $categories->flatMap(fn (ItemCategory $category) => $category->items->modelKeys())->all();

        $prices = Price::query()
            ->where('price_list_id', PriceList::default()->id)
            ->whereIn('item_id', $itemIds)
            ->whereIn('service_type_id', $serviceTypes->modelKeys())
            ->get()
            ->map(fn (Price $price): array => [
                'item_id' => $price->item_id,
                'service_type_id' => $price->service_type_id,
                'price' => $price->price,
            ])
            ->values();

        return response()->json(['data' => [
            'service_types' => ServiceTypeResource::collection($serviceTypes)->resolve($request),
            'categories' => ItemCategoryResource::collection($categories)->resolve($request),
            'prices' => $prices,
            'upcharges' => UpchargeResource::collection(Upcharge::query()->active()->ordered()->get())->resolve($request),
            'turnaround_tiers' => TurnaroundTierResource::collection(TurnaroundTier::query()->active()->ordered()->get())->resolve($request),
            'defect_types' => DefectTypeResource::collection(DefectType::query()->active()->ordered()->get())->resolve($request),
            'colors' => ColorResource::collection(Color::query()->active()->ordered()->get())->resolve($request),
        ]]);
    }
}
