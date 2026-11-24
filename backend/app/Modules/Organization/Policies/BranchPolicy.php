<?php

declare(strict_types=1);

namespace App\Modules\Organization\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Organization\Models\Branch;

final class BranchPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('branches.view');
    }

    public function view(User $user, Branch $branch): bool
    {
        return $user->can('branches.view') && $user->canAccessBranch($branch);
    }

    public function create(User $user): bool
    {
        return $user->can('branches.create');
    }

    public function update(User $user, Branch $branch): bool
    {
        return $user->can('branches.update') && $user->canAccessBranch($branch);
    }

    public function delete(User $user, Branch $branch): bool
    {
        return $user->can('branches.delete') && $user->canAccessBranch($branch);
    }
}
