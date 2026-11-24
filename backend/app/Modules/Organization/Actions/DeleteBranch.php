<?php

declare(strict_types=1);

namespace App\Modules\Organization\Actions;

use App\Modules\Organization\Models\Branch;
use App\Support\Exceptions\ApiException;

final class DeleteBranch
{
    public function handle(Branch $branch): void
    {
        // An organization without any branch cannot take orders.
        if (Branch::query()->whereKeyNot($branch->getKey())->doesntExist()) {
            throw ApiException::conflict('branch_last');
        }

        $branch->delete();
    }
}
