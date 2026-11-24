<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Http\Requests\SaveDefectTypeRequest;
use App\Modules\Catalog\Http\Resources\DefectTypeResource;
use App\Modules\Catalog\Models\DefectType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class DefectTypeController
{
    /**
     * List defect types
     *
     * All of them, inactive included, in display order.
     */
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('catalog.view');

        return DefectTypeResource::collection(DefectType::query()->ordered()->get());
    }

    /**
     * Create a defect type
     */
    public function store(SaveDefectTypeRequest $request): JsonResponse
    {
        $defectType = DefectType::query()->create($request->validated());

        return (new DefectTypeResource($defectType->refresh()))->response()->setStatusCode(201);
    }

    /**
     * Update a defect type
     */
    public function update(SaveDefectTypeRequest $request, DefectType $defectType): DefectTypeResource
    {
        $defectType->update($request->validated());

        return new DefectTypeResource($defectType);
    }

    /**
     * Delete a defect type
     *
     * Orders that already use it keep their copy of the name and price.
     */
    public function destroy(DefectType $defectType): Response
    {
        Gate::authorize('catalog.manage');

        $defectType->delete();

        return response()->noContent();
    }
}
