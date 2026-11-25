<?php

declare(strict_types=1);

namespace App\Modules\Orders\Enums;

/**
 * The coarse state of an order. Pickup and delivery states are used from
 * phase 4 (online orders); counter orders start at "received".
 */
enum OrderStatus: string
{
    case Draft = 'draft';
    case PendingPickup = 'pending_pickup';
    case PickedUp = 'picked_up';
    case Received = 'received';
    case InProcess = 'in_process';
    case PartiallyReady = 'partially_ready';
    case Ready = 'ready';
    case OutForDelivery = 'out_for_delivery';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /** Still in the shop: not handed back and not cancelled. */
    public function isOpen(): bool
    {
        return ! in_array($this, [self::Completed, self::Cancelled], true);
    }

    /**
     * States an order may move to from this one by an explicit transition.
     * An order becomes ready only through assembly (every piece gathered
     * and racked), and completed through handover; both have their own
     * endpoints. Moving a ready order back to "in process" sends it back
     * to the workshop.
     *
     * @return list<self>
     */
    public function transitions(): array
    {
        return match ($this) {
            self::Received => [self::InProcess, self::Cancelled],
            self::InProcess => [self::Cancelled],
            self::PartiallyReady, self::Ready => [self::InProcess],
            default => [],
        };
    }

    public function canMoveTo(self $target): bool
    {
        return in_array($target, $this->transitions(), true);
    }

    /** Lines can still be changed (nothing has been worked on yet). */
    public function isEditable(): bool
    {
        return $this === self::Received;
    }
}
