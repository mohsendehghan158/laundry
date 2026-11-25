<?php

declare(strict_types=1);

namespace App\Modules\Production\Support;

use App\Modules\Production\Events\BoardChanged;
use Throwable;

final class Board
{
    /**
     * Tells the boards of these branches to reload. Never fails the
     * request: without a socket server boards simply keep polling.
     *
     * @param  list<int|null>  $branchIds
     */
    public static function changed(array $branchIds, string $reason, ?int $orderId = null): void
    {
        $branchIds = array_values(array_unique(array_filter($branchIds, fn (?int $id): bool => $id !== null)));

        if ($branchIds === []) {
            return;
        }

        try {
            broadcast(new BoardChanged($branchIds, $reason, $orderId));
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
