<?php

declare(strict_types=1);

namespace App\Modules\Production\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Something on a branch's production board changed (a scan, an order
 * gathered, racked or created): boards listening on the branch's channel
 * reload. Carries no order details, so nothing leaks to the socket.
 */
final class BoardChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    /** Sent only once the change is committed. */
    public bool $afterCommit = true;

    /**
     * @param  list<int>  $branchIds
     */
    public function __construct(public readonly array $branchIds, public readonly string $reason, public readonly ?int $orderId = null) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return array_map(fn (int $id): PrivateChannel => new PrivateChannel("plant.{$id}.board"), array_values(array_unique($this->branchIds)));
    }

    public function broadcastAs(): string
    {
        return 'board.changed';
    }

    /**
     * @return array{reason: string, order_id: int|null}
     */
    public function broadcastWith(): array
    {
        return ['reason' => $this->reason, 'order_id' => $this->orderId];
    }
}
