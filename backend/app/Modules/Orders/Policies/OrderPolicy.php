<?php

declare(strict_types=1);

namespace App\Modules\Orders\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Orders\Models\Order;

final class OrderPolicy
{
    public function view(User $user, Order $order): bool
    {
        return $user->can('orders.view') && $this->inReach($user, $order);
    }

    public function update(User $user, Order $order): bool
    {
        return $user->can('orders.update') && $this->inReach($user, $order);
    }

    public function cancel(User $user, Order $order): bool
    {
        return $user->can('orders.cancel') && $this->inReach($user, $order);
    }

    private function inReach(User $user, Order $order): bool
    {
        return $user->hasAllBranchAccess() || $user->branches()->whereKey($order->branch_id)->exists();
    }
}
