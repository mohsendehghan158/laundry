<?php

declare(strict_types=1);

namespace App\Modules\Logistics\Support;

use App\Modules\Logistics\Actions\SyncSlots;
use App\Modules\Logistics\Models\Slot;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/** The slots of a branch a customer can still book. */
final readonly class SlotAvailability
{
    public function __construct(private SyncSlots $sync, private LogisticsSettings $settings) {}

    /**
     * Open slots with room, from the lead time on, soonest first.
     *
     * @return Collection<int, Slot>
     */
    public function forBranch(int $branchId): Collection
    {
        $this->sync->handle($branchId);

        $timezone = $this->settings->timezone();
        $today = $this->settings->today();
        $earliest = CarbonImmutable::now($timezone)->addMinutes($this->settings->leadMinutes());

        return Slot::query()
            ->where('branch_id', $branchId)
            ->where('is_closed', false)
            ->whereColumn('booked', '<', 'capacity')
            ->whereBetween('date', [$today->toDateString(), $today->addDays($this->settings->bookingDays() - 1)->toDateString()])
            ->orderBy('date')
            ->orderBy('starts_at')
            ->get()
            ->filter(fn (Slot $slot): bool => $slot->startsAt($timezone)->greaterThanOrEqualTo($earliest))
            ->values();
    }

    /** Whether a customer can still book this slot now. */
    public function bookable(Slot $slot): bool
    {
        return $this->forBranch($slot->branch_id)->contains('id', $slot->id);
    }
}
