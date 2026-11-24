<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Actions\UpdatePrices;
use App\Modules\Catalog\Http\Requests\UpdatePricesRequest;
use App\Modules\Catalog\Models\Price;
use App\Modules\Catalog\Models\PriceList;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class PriceController
{
    /**
     * Show the price matrix
     *
     * Every priced item × service pair of the default price list, in
     * whole rials per unit. A missing pair means the service is not
     * offered for that item.
     *
     * @response array{data: list<array{item_id: int, service_type_id: int, price: int}>}
     */
    public function index(): JsonResponse
    {
        Gate::authorize('catalog.view');

        return response()->json(['data' => $this->matrix()]);
    }

    /**
     * Set prices
     *
     * Changes only the listed cells; send `price: null` to remove one.
     * Returns the whole matrix afterwards.
     *
     * @response array{data: list<array{item_id: int, service_type_id: int, price: int}>}
     */
    public function update(UpdatePricesRequest $request, UpdatePrices $updatePrices): JsonResponse
    {
        /** @var list<array{item_id: int, service_type_id: int, price: int|null}> $cells */
        $cells = $request->validated('prices');

        $updatePrices->handle($request->actor(), $cells);

        return response()->json(['data' => $this->matrix()]);
    }

    /**
     * @return list<array{item_id: int, service_type_id: int, price: int}>
     */
    private function matrix(): array
    {
        return Price::query()
            ->where('price_list_id', PriceList::default()->id)
            ->orderBy('item_id')
            ->orderBy('service_type_id')
            ->get()
            ->map(fn (Price $price): array => [
                'item_id' => $price->item_id,
                'service_type_id' => $price->service_type_id,
                'price' => $price->price,
            ])
            ->values()
            ->all();
    }
}
