<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Http\Requests\SaveServiceTypeRequest;
use App\Modules\Catalog\Http\Resources\ServiceTypeResource;
use App\Modules\Catalog\Models\ServiceType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ServiceTypeController
{
    /**
     * List service types
     *
     * All of them, inactive included, in display order.
     */
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('catalog.view');

        return ServiceTypeResource::collection(ServiceType::query()->ordered()->get());
    }

    /**
     * Create a service type
     */
    public function store(SaveServiceTypeRequest $request): JsonResponse
    {
        $serviceType = ServiceType::query()->create($request->validated());

        return (new ServiceTypeResource($serviceType->refresh()))->response()->setStatusCode(201);
    }

    /**
     * Update a service type
     */
    public function update(SaveServiceTypeRequest $request, ServiceType $serviceType): ServiceTypeResource
    {
        $serviceType->update($request->validated());

        return new ServiceTypeResource($serviceType);
    }

    /**
     * Delete a service type
     *
     * Orders that already use it keep their copy of the name and price.
     */
    public function destroy(ServiceType $serviceType): Response
    {
        Gate::authorize('catalog.manage');

        $serviceType->delete();

        return response()->noContent();
    }
}
