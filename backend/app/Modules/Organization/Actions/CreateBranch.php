<?php

declare(strict_types=1);

namespace App\Modules\Organization\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Organization\Models\Branch;
use Illuminate\Support\Facades\DB;

final class CreateBranch
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $actor, array $data): Branch
    {
        return DB::transaction(function () use ($actor, $data): Branch {
            $branch = Branch::query()->create($data);

            // Someone limited to their own branches would otherwise lose
            // sight of the branch they just created.
            if (! $actor->hasAllBranchAccess()) {
                $branch->users()->attach($actor);
            }

            return $branch;
        });
    }
}
