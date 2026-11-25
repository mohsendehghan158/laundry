<?php

declare(strict_types=1);

namespace App\Modules\Logistics\Actions;

use App\Modules\Logistics\Models\Slot;
use App\Support\Exceptions\ApiException;
use Illuminate\Support\Facades\DB;

/**
 * Takes one place in a slot, or fails with 409 `slot_full` when it is
 * full or closed. One UPDATE, so two customers can never take the last
 * place together. Whether the slot is still bookable in time is for the
 * caller to check (SlotAvailability).
 */
final class ReserveSlot
{
    public function handle(Slot $slot): Slot
    {
        $taken = DB::table('slots')
            ->where('id', $slot->id)
            ->where('is_closed', false)
            ->whereColumn('booked', '<', 'capacity')
            ->increment('booked');

        if ($taken === 0) {
            throw new ApiException('slot_full', __('api.errors.slot_full'), 409);
        }

        return $slot->refresh();
    }

    /** Gives a place back, e.g. when an online order is cancelled. */
    public function release(Slot $slot): Slot
    {
        DB::table('slots')->where('id', $slot->id)->where('booked', '>', 0)->decrement('booked');

        return $slot->refresh();
    }
}
