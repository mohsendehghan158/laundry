<?php

declare(strict_types=1);

namespace App\Modules\Production\Http\Controllers;

use App\Modules\Identity\Models\User;
use App\Modules\Production\Http\Requests\SaveVendorRequest;
use App\Modules\Production\Http\Resources\VendorResource;
use App\Modules\Production\Models\Vendor;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class VendorController
{
    /**
     * List vendors
     */
    public function index(#[CurrentUser] User $actor): AnonymousResourceCollection
    {
        abort_unless($actor->can('production.outsource') || $actor->can('production.manage'), 403);

        return VendorResource::collection(Vendor::query()->orderBy('name')->get());
    }

    /**
     * Add a vendor
     */
    public function store(SaveVendorRequest $request): JsonResponse
    {
        return (new VendorResource(Vendor::query()->create($request->validated())->refresh()))->response()->setStatusCode(201);
    }

    /**
     * Update a vendor
     */
    public function update(SaveVendorRequest $request, Vendor $vendor): VendorResource
    {
        $vendor->update($request->validated());

        return new VendorResource($vendor);
    }

    /**
     * Delete a vendor
     *
     * Past shipments keep their vendor.
     */
    public function destroy(Vendor $vendor): Response
    {
        Gate::authorize('production.manage');
        $vendor->delete();

        return response()->noContent();
    }
}
