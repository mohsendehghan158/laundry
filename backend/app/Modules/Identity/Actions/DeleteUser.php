<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Actions\Concerns\GuardsOwners;
use App\Modules\Identity\Models\User;
use App\Support\Exceptions\ApiException;

final class DeleteUser
{
    use GuardsOwners;

    public function handle(User $actor, User $user): void
    {
        if ($user->is($actor)) {
            throw ApiException::conflict('user_cannot_delete_self');
        }

        if ($user->isOwner()) {
            $this->ensureAnotherActiveOwnerExists($user);
        }

        $user->delete();
    }
}
