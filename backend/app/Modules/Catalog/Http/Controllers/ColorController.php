<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Http\Requests\SaveColorRequest;
use App\Modules\Catalog\Http\Resources\ColorResource;
use App\Modules\Catalog\Models\Color;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ColorController
{
    /**
     * List colors
     *
     * All of them, inactive included, in display order.
     */
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('catalog.view');

        return ColorResource::collection(Color::query()->ordered()->get());
    }

    /**
     * Create a color
     */
    public function store(SaveColorRequest $request): JsonResponse
    {
        $color = Color::query()->create($request->validated());

        return (new ColorResource($color->refresh()))->response()->setStatusCode(201);
    }

    /**
     * Update a color
     */
    public function update(SaveColorRequest $request, Color $color): ColorResource
    {
        $color->update($request->validated());

        return new ColorResource($color);
    }

    /**
     * Delete a color
     *
     * Orders that already use it keep their copy of the name and price.
     */
    public function destroy(Color $color): Response
    {
        Gate::authorize('catalog.manage');

        $color->delete();

        return response()->noContent();
    }
}
