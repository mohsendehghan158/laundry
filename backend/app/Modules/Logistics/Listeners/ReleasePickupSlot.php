<?php

declare(strict_types=1);

namespace App\Modules\Logistics\Listeners;

use App\Modules\Logistics\Actions\ReserveSlot;
use App\Modules\Logistics\Models\Slot;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Events\OrderStatusChanged;

/**
 * Gives the booked pickup window its place back when an order is
 * cancelled before it was picked up.
 */
final readonly class ReleasePickupSlot
{
    public function __construct(private ReserveSlot $slots) {}

    public function handle(OrderStatusChanged $event): void
    {
        if ($event->to !== OrderStatus::Cancelled || $event->from !== OrderStatus::PendingPickup || $event->order->pickup_slot_id === null) {
            return;
        }

        // The slot may belong to no organization the request runs in (a
        // console command), so it is looked up without the tenant scope.
        $slot = Slot::query()->withoutGlobalScopes()->find($event->order->pickup_slot_id);

        if ($slot !== null) {
            $this->slots->release($slot);
        }
    }
}
