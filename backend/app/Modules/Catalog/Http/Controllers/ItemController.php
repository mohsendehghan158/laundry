<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Http\Requests\SaveItemRequest;
use App\Modules\Catalog\Http\Resources\ItemResource;
use App\Modules\Catalog\Models\Item;
use App\Modules\Catalog\Models\ItemCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ItemController
{
    /**
     * List items
     *
     * All of them, inactive included, grouped by category in display order.
     */
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('catalog.view');

        $items = Item::query()
            ->orderBy(ItemCategory::query()->select('sort_order')->whereColumn('item_categories.id', 'items.item_category_id'))
            ->orderBy('item_category_id')
            ->ordered()
            ->get();

        return ItemResource::collection($items);
    }

    /**
     * Create an item
     */
    public function store(SaveItemRequest $request): JsonResponse
    {
        $item = Item::query()->create($request->validated());

        return (new ItemResource($item->refresh()))->response()->setStatusCode(201);
    }

    /**
     * Update an item
     */
    public function update(SaveItemRequest $request, Item $item): ItemResource
    {
        $item->update($request->validated());

        return new ItemResource($item);
    }

    /**
     * Delete an item
     *
     * Orders that already use it keep their copy of the name and price.
     */
    public function destroy(Item $item): Response
    {
        Gate::authorize('catalog.manage');

        $item->delete();

        return response()->noContent();
    }
}
