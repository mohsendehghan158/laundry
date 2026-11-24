<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Http\Requests\SaveUpchargeRequest;
use App\Modules\Catalog\Http\Resources\UpchargeResource;
use App\Modules\Catalog\Models\Upcharge;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class UpchargeController
{
    /**
     * List upcharges
     *
     * All of them, inactive included, in display order.
     */
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('catalog.view');

        return UpchargeResource::collection(Upcharge::query()->ordered()->get());
    }

    /**
     * Create an upcharge
     */
    public function store(SaveUpchargeRequest $request): JsonResponse
    {
        $upcharge = Upcharge::query()->create($request->validated());

        return (new UpchargeResource($upcharge->refresh()))->response()->setStatusCode(201);
    }

    /**
     * Update an upcharge
     */
    public function update(SaveUpchargeRequest $request, Upcharge $upcharge): UpchargeResource
    {
        $upcharge->update($request->validated());

        return new UpchargeResource($upcharge);
    }

    /**
     * Delete an upcharge
     *
     * Orders that already use it keep their copy of the name and price.
     */
    public function destroy(Upcharge $upcharge): Response
    {
        Gate::authorize('catalog.manage');

        $upcharge->delete();

        return response()->noContent();
    }
}
