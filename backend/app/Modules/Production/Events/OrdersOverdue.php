<?php

declare(strict_types=1);

namespace App\Modules\Production\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Orders that have just passed their due time while still in work, for a
 * branch's production board to flag.
 */
final class OrdersOverdue implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    /**
     * @param  list<string>  $tickets
     */
    public function __construct(public readonly int $branchId, public readonly array $tickets) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("plant.{$this->branchId}.board")];
    }

    public function broadcastAs(): string
    {
        return 'orders.overdue';
    }

    /**
     * @return array{tickets: list<string>}
     */
    public function broadcastWith(): array
    {
        return ['tickets' => $this->tickets];
    }
}
