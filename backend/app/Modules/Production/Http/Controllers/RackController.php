<?php

declare(strict_types=1);

namespace App\Modules\Production\Http\Controllers;

use App\Modules\Identity\Models\User;
use App\Modules\Orders\Enums\PieceStatus;
use App\Modules\Organization\Models\Branch;
use App\Modules\Production\Http\Requests\SaveRackRequest;
use App\Modules\Production\Http\Resources\RackResource;
use App\Modules\Production\Models\Rack;
use App\Support\Exceptions\ApiException;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class RackController
{
    /**
     * List racks
     *
     * Racks of the user's branches with their slots and the orders waiting
     * in each.
     */
    public function index(#[CurrentUser] User $actor): AnonymousResourceCollection
    {
        abort_unless($actor->can('production.view') || $actor->can('orders.view'), 403);

        $racks = Rack::query()
            ->whereIn('branch_id', Branch::query()->visibleTo($actor)->select('id'))
            ->with(['slots.pieces' => fn ($query) => $query->where('status', PieceStatus::Ready)->with('order:id,ticket_number')])
            ->orderBy('branch_id')
            ->ordered()
            ->get();

        return RackResource::collection($racks);
    }

    /**
     * Create a rack
     *
     * Its slots are numbered from 1: code "A" with 3 slots gives A-01,
     * A-02 and A-03.
     */
    public function store(SaveRackRequest $request): JsonResponse
    {
        $branch = Branch::query()->findOrFail($request->integer('branch_id'));
        abort_unless($request->actor()->canAccessBranch($branch), 404);

        $rack = DB::transaction(function () use ($request): Rack {
            $rack = Rack::query()->create($request->safe()->except('slot_count'));
            $this->resize($rack, $request->integer('slot_count'));

            return $rack;
        });

        return (new RackResource($this->load($rack)))->response()->setStatusCode(201);
    }

    /**
     * Update a rack
     *
     * Slots can be added; removing slots fails with 409 `rack_slots_in_use`
     * while orders wait in them.
     */
    public function update(SaveRackRequest $request, Rack $rack): RackResource
    {
        $this->reach($request->actor(), $rack);

        DB::transaction(function () use ($request, $rack): void {
            $rack->update($request->safe()->except('slot_count'));
            if ($request->has('slot_count')) {
                $this->resize($rack, $request->integer('slot_count'));
            }
        });

        return new RackResource($this->load($rack));
    }

    /**
     * Delete a rack
     *
     * Fails with 409 `rack_slots_in_use` while orders wait in it.
     */
    public function destroy(Rack $rack, #[CurrentUser] User $actor): Response
    {
        Gate::authorize('production.manage');
        $this->reach($actor, $rack);

        if ($rack->slots()->whereHas('pieces', fn ($query) => $query->where('status', PieceStatus::Ready))->exists()) {
            throw ApiException::conflict('rack_slots_in_use');
        }

        $rack->delete();

        return response()->noContent();
    }

    private function resize(Rack $rack, int $count): void
    {
        $current = $rack->slots()->count();

        for ($position = $current + 1; $position <= $count; $position++) {
            $rack->slots()->create(['organization_id' => $rack->organization_id, 'position' => $position, 'code' => $rack->slotCode($position)]);
        }

        if ($count < $current) {
            $removed = $rack->slots()->where('position', '>', $count);
            if ((clone $removed)->whereHas('pieces', fn ($query) => $query->where('status', PieceStatus::Ready))->exists()) {
                throw ApiException::conflict('rack_slots_in_use');
            }
            $removed->delete();
        }
    }

    private function load(Rack $rack): Rack
    {
        return $rack->refresh()->load(['slots.pieces' => fn ($query) => $query->where('status', PieceStatus::Ready)->with('order:id,ticket_number')]);
    }

    private function reach(User $actor, Rack $rack): void
    {
        abort_unless($rack->branch !== null && $actor->canAccessBranch($rack->branch), 404);
    }
}
