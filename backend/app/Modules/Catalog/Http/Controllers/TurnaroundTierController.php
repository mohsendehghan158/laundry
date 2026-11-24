<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Actions\SaveTurnaroundTier;
use App\Modules\Catalog\Http\Requests\SaveTurnaroundTierRequest;
use App\Modules\Catalog\Http\Resources\TurnaroundTierResource;
use App\Modules\Catalog\Models\TurnaroundTier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class TurnaroundTierController
{
    /**
     * List turnaround tiers
     *
     * All of them, inactive included, in display order.
     */
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('catalog.view');

        return TurnaroundTierResource::collection(TurnaroundTier::query()->ordered()->get());
    }

    /**
     * Create a turnaround tier
     *
     * Marking it as the default clears the flag on the other tiers.
     */
    public function store(SaveTurnaroundTierRequest $request, SaveTurnaroundTier $saveTier): JsonResponse
    {
        $tier = $saveTier->handle(null, $request->validated());

        return (new TurnaroundTierResource($tier->refresh()))->response()->setStatusCode(201);
    }

    /**
     * Update a turnaround tier
     */
    public function update(SaveTurnaroundTierRequest $request, TurnaroundTier $turnaroundTier, SaveTurnaroundTier $saveTier): TurnaroundTierResource
    {
        return new TurnaroundTierResource($saveTier->handle($turnaroundTier, $request->validated()));
    }

    /**
     * Delete a turnaround tier
     */
    public function destroy(TurnaroundTier $turnaroundTier): Response
    {
        Gate::authorize('catalog.manage');

        $turnaroundTier->delete();

        return response()->noContent();
    }
}
