<?php

declare(strict_types=1);

namespace App\Modules\Identity\Policies;

use App\Modules\Identity\Models\User;

/**
 * Owners bypass these checks entirely (see Gate::before); rules that must
 * hold even for owners live in the user actions.
 */
final class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('users.view');
    }

    public function view(User $actor, User $target): bool
    {
        return $actor->can('users.view') && $this->sees($actor, $target);
    }

    public function create(User $actor): bool
    {
        return $actor->can('users.create');
    }

    public function update(User $actor, User $target): bool
    {
        return $actor->can('users.update') && $this->sees($actor, $target) && ! $target->isOwner();
    }

    public function delete(User $actor, User $target): bool
    {
        return $actor->can('users.delete') && $this->sees($actor, $target) && ! $target->isOwner();
    }

    private function sees(User $actor, User $target): bool
    {
        return User::query()->visibleTo($actor)->whereKey($target->getKey())->exists();
    }
}
