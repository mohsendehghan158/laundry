<?php

declare(strict_types=1);

use App\Modules\Identity\Models\User;
use App\Modules\Organization\Models\Branch;
use Illuminate\Support\Facades\Broadcast;

// A branch's production board: anyone who may see the workshop and works in that branch.
Broadcast::channel('plant.{branchId}.board', function (User $user, string $branchId): bool {
    $branch = Branch::query()->find((int) $branchId);

    return $branch !== null && $user->can('production.view') && $user->canAccessBranch($branch);
});
