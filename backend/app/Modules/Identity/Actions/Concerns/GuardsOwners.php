<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions\Concerns;

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Support\PermissionRegistry;
use App\Support\Exceptions\ApiException;

trait GuardsOwners
{
    /**
     * An organization must always keep one owner who can sign in,
     * otherwise nobody could manage roles or users again.
     */
    private function ensureAnotherActiveOwnerExists(User $user): void
    {
        $others = User::role(PermissionRegistry::OWNER)
            ->where('is_active', true)
            ->whereKeyNot($user->getKey())
            ->exists();

        if (! $others) {
            throw ApiException::conflict('user_last_owner');
        }
    }
}
