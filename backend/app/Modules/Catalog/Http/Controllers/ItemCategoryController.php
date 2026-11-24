<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Actions\DeleteItemCategory;
use App\Modules\Catalog\Http\Requests\SaveItemCategoryRequest;
use App\Modules\Catalog\Http\Resources\ItemCategoryResource;
use App\Modules\Catalog\Models\ItemCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ItemCategoryController
{
    /**
     * List item categories
     *
     * All of them, inactive included, in display order.
     */
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('catalog.view');

        return ItemCategoryResource::collection(ItemCategory::query()->ordered()->get());
    }

    /**
     * Create an item category
     */
    public function store(SaveItemCategoryRequest $request): JsonResponse
    {
        $category = ItemCategory::query()->create($request->validated());

        return (new ItemCategoryResource($category->refresh()))->response()->setStatusCode(201);
    }

    /**
     * Update an item category
     */
    public function update(SaveItemCategoryRequest $request, ItemCategory $itemCategory): ItemCategoryResource
    {
        $itemCategory->update($request->validated());

        return new ItemCategoryResource($itemCategory);
    }

    /**
     * Delete an item category
     *
     * Fails with 409 `category_has_items` while items still belong to it.
     */
    public function destroy(ItemCategory $itemCategory, DeleteItemCategory $deleteItemCategory): Response
    {
        Gate::authorize('catalog.manage');

        $deleteItemCategory->handle($itemCategory);

        return response()->noContent();
    }
}
