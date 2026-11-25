<?php

declare(strict_types=1);

namespace App\Modules\Logistics\Http\Controllers;

use App\Modules\Identity\Models\User;
use App\Modules\Logistics\Actions\SyncSlots;
use App\Modules\Logistics\Http\Requests\UpdateSlotRequest;
use App\Modules\Logistics\Http\Resources\SlotResource;
use App\Modules\Logistics\Models\Slot;
use App\Modules\Logistics\Support\LogisticsSettings;
use App\Modules\Organization\Models\Branch;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class SlotController
{
    /**
     * List a branch's upcoming slots
     *
     * Every slot from today through the bookable days, with its bookings,
     * closed ones included.
     */
    #[QueryParameter('branch_id', 'Required: the branch whose slots to list.', type: 'int')]
    public function index(Request $request, #[CurrentUser] User $actor, SyncSlots $sync, LogisticsSettings $settings): AnonymousResourceCollection
    {
        Gate::authorize('logistics.view');
        $request->validate(['branch_id' => ['required', 'integer']]);
        $branch = Branch::query()->find($request->integer('branch_id'));
        abort_unless($branch !== null && $actor->canAccessBranch($branch), 404);

        $sync->handle($branch->id);
        $today = $settings->today();

        $slots = Slot::query()
            ->where('branch_id', $branch->id)
            ->whereBetween('date', [$today->toDateString(), $today->addDays($settings->bookingDays() - 1)->toDateString()])
            ->orderBy('date')
            ->orderBy('starts_at')
            ->get();

        return SlotResource::collection($slots);
    }

    /**
     * Change one slot
     *
     * Close it (a holiday, a driver off sick) or change its capacity, but
     * never below the places already booked.
     */
    public function update(UpdateSlotRequest $request, Slot $slot): SlotResource
    {
        abort_unless($slot->branch !== null && $request->actor()->canAccessBranch($slot->branch), 404);

        $slot->update($request->validated());

        return new SlotResource($slot);
    }
}
