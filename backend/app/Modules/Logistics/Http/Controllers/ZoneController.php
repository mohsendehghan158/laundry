<?php

declare(strict_types=1);

namespace App\Modules\Logistics\Http\Controllers;

use App\Modules\Identity\Models\User;
use App\Modules\Logistics\Http\Requests\SaveZoneRequest;
use App\Modules\Logistics\Http\Resources\ZoneResource;
use App\Modules\Logistics\Models\Zone;
use App\Modules\Organization\Models\Branch;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use LogicException;

class ZoneController
{
    /**
     * List service zones
     *
     * Zones served by the branches the user works in, inactive ones included.
     */
    #[QueryParameter('branch_id', 'Only zones served by this branch.', type: 'int')]
    public function index(Request $request, #[CurrentUser] User $actor): AnonymousResourceCollection
    {
        Gate::authorize('logistics.view');

        $zones = Zone::query()
            ->with('branch')
            ->whereIn('branch_id', Branch::query()->visibleTo($actor)->select('id'))
            ->when($request->filled('branch_id'), fn ($query) => $query->where('branch_id', $request->integer('branch_id')))
            ->orderBy('id')
            ->get();

        return ZoneResource::collection($zones);
    }

    /**
     * Create a service zone
     *
     * Where zones overlap, an address belongs to the one created first.
     */
    public function store(SaveZoneRequest $request): JsonResponse
    {
        $this->reachBranch($request->actor(), $request->integer('branch_id'));

        $zone = new Zone($request->safe()->except('polygon'));
        $zone->setShape($request->polygon() ?? throw new LogicException('The polygon is validated as required.'));
        $zone->save();

        return (new ZoneResource($zone->load('branch')))->response()->setStatusCode(201);
    }

    /**
     * Update a service zone
     */
    public function update(SaveZoneRequest $request, Zone $zone): ZoneResource
    {
        $this->reachBranch($request->actor(), $zone->branch_id);
        if ($request->has('branch_id')) {
            $this->reachBranch($request->actor(), $request->integer('branch_id'));
        }

        $zone->fill($request->safe()->except('polygon'));
        $polygon = $request->polygon();
        if ($polygon !== null) {
            $zone->setShape($polygon);
        }
        $zone->save();

        return new ZoneResource($zone->load('branch'));
    }

    /**
     * Delete a service zone
     */
    public function destroy(Zone $zone, #[CurrentUser] User $actor): Response
    {
        Gate::authorize('logistics.manage');
        $this->reachBranch($actor, $zone->branch_id);

        $zone->delete();

        return response()->noContent();
    }

    private function reachBranch(User $actor, int $branchId): void
    {
        $branch = Branch::query()->find($branchId);
        abort_unless($branch !== null && $actor->canAccessBranch($branch), 404);
    }
}
