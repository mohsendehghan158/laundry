<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Http\Requests\SavePriceListRequest;
use App\Modules\Catalog\Models\Price;
use App\Modules\Catalog\Models\PriceList;
use App\Support\Exceptions\ApiException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class PriceListController
{
    /**
     * List price lists
     *
     * The default list first, then contract lists, with how many prices
     * each has.
     *
     * @response array{data: list<array{id: int, name: string, is_default: bool, prices_count: int}>}
     */
    public function index(): JsonResponse
    {
        Gate::authorize('catalog.view');
        PriceList::default();

        return response()->json(['data' => $this->lists()]);
    }

    /**
     * Create a contract price list
     *
     * Empty, or with `percent` set, a copy of every default price changed by
     * that percent (−10 for 10% off), rounded to whole rials.
     *
     * @response 201 array{data: array{id: int, name: string, is_default: bool, prices_count: int}}
     */
    public function store(SavePriceListRequest $request): JsonResponse
    {
        $list = DB::transaction(function () use ($request): PriceList {
            $list = PriceList::query()->create(['name' => (string) $request->validated('name'), 'is_default' => false]);
            $percent = $request->validated('percent');

            if ($percent !== null) {
                foreach (PriceList::default()->prices()->get() as $price) {
                    Price::query()->create([
                        'price_list_id' => $list->id,
                        'item_id' => $price->item_id,
                        'service_type_id' => $price->service_type_id,
                        'price' => max(0, (int) round($price->price * (100 + (float) $percent) / 100)),
                    ]);
                }
            }

            activity('catalog')->causedBy($request->actor())->performedOn($list)->event('created')->log('created');

            return $list;
        });

        return response()->json(['data' => $this->lists($list->id)[0]], 201);
    }

    /**
     * Rename a price list
     *
     * @response array{data: array{id: int, name: string, is_default: bool, prices_count: int}}
     */
    public function update(SavePriceListRequest $request, PriceList $priceList): JsonResponse
    {
        $priceList->update(['name' => (string) $request->validated('name')]);

        return response()->json(['data' => $this->lists($priceList->id)[0]]);
    }

    /**
     * Delete a contract price list
     *
     * Companies using it go back to default prices. The default list
     * cannot be deleted (409 `price_list_default`).
     */
    public function destroy(PriceList $priceList): Response
    {
        Gate::authorize('catalog.manage');

        if ($priceList->is_default) {
            throw ApiException::conflict('price_list_default');
        }

        $priceList->delete();

        return response()->noContent();
    }

    /**
     * @return list<array{id: int, name: string, is_default: bool, prices_count: int}>
     */
    private function lists(?int $id = null): array
    {
        return array_values(PriceList::query()
            ->withCount('prices')
            ->when($id !== null, fn ($query) => $query->whereKey($id))
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get()
            ->map(fn (PriceList $list): array => [
                'id' => $list->id,
                'name' => $list->name,
                'is_default' => $list->is_default,
                'prices_count' => (int) $list->getAttribute('prices_count'),
            ])
            ->all());
    }
}
