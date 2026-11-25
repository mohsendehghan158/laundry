<?php

declare(strict_types=1);

namespace App\Modules\Production\Http\Controllers;

use App\Modules\Identity\Models\User;
use App\Modules\Organization\Models\Branch;
use App\Modules\Production\Http\Requests\SaveStationRequest;
use App\Modules\Production\Http\Resources\StationResource;
use App\Modules\Production\Models\PieceScan;
use App\Modules\Production\Models\Station;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class StationController
{
    /**
     * List stations
     *
     * Stations of the branches the user works in, in workflow order,
     * inactive ones included.
     */
    #[QueryParameter('branch_id', 'Only this branch\'s stations.', type: 'int')]
    public function index(Request $request, #[CurrentUser] User $actor): AnonymousResourceCollection
    {
        Gate::authorize('production.view');

        $stations = Station::query()
            ->whereIn('branch_id', Branch::query()->visibleTo($actor)->select('id'))
            ->when($request->filled('branch_id'), fn ($query) => $query->where('branch_id', $request->integer('branch_id')))
            ->orderBy('branch_id')
            ->ordered()
            ->get();

        return StationResource::collection($stations);
    }

    /**
     * Create a station
     */
    public function store(SaveStationRequest $request): JsonResponse
    {
        $branch = Branch::query()->findOrFail($request->integer('branch_id'));
        abort_unless($request->actor()->canAccessBranch($branch), 404);

        $station = Station::query()->create($request->validated());

        return (new StationResource($station->refresh()))->response()->setStatusCode(201);
    }

    /**
     * Update a station
     */
    public function update(SaveStationRequest $request, Station $station): StationResource
    {
        $this->reach($request->actor(), $station);
        $station->update($request->validated());

        return new StationResource($station);
    }

    /**
     * Delete a station
     *
     * Its scans stay in the pieces' history.
     */
    public function destroy(Station $station, #[CurrentUser] User $actor): Response
    {
        Gate::authorize('production.manage');
        $this->reach($actor, $station);

        $station->delete();

        return response()->noContent();
    }

    /**
     * Station activity
     *
     * Today's scan counts (the user's and everyone's) and the latest scans,
     * for the counter on the station screen.
     *
     * @response array{data: array{today: array{mine: int, total: int}, recent: list<array{id: int, tag_code: string, item_name: string, event: string, user_name: string|null, created_at: string}>}}
     */
    public function activity(Station $station, #[CurrentUser] User $actor): JsonResponse
    {
        Gate::authorize('production.view');
        $this->reach($actor, $station);

        $today = PieceScan::query()->where('station_id', $station->id)->where('created_at', '>=', now()->startOfDay());
        $recent = PieceScan::query()
            ->with(['piece.line', 'user'])
            ->where('station_id', $station->id)
            ->latest('id')
            ->limit(15)
            ->get()
            ->map(fn (PieceScan $scan): array => [
                'id' => $scan->id,
                'tag_code' => $scan->piece->tag_code,
                'item_name' => $scan->piece->line->item_name,
                'event' => $scan->event->value,
                'user_name' => $scan->user?->name,
                'created_at' => $scan->created_at->toIso8601String(),
            ])
            ->all();

        return response()->json(['data' => [
            'today' => ['mine' => (clone $today)->where('user_id', $actor->id)->count(), 'total' => $today->count()],
            'recent' => $recent,
        ]]);
    }

    private function reach(User $actor, Station $station): void
    {
        abort_unless($station->branch !== null && $actor->canAccessBranch($station->branch), 404);
    }
}
