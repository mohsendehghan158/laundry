<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

final class CreateUser
{
    /**
     * @param  array<string, mixed>  $data  validated by StoreUserRequest
     */
    public function handle(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $user = User::query()->create(Arr::except($data, ['roles', 'branch_ids']));

            $user->syncRoles($data['roles']);
            $user->branches()->sync($data['branch_ids'] ?? []);

            return $user;
        });
    }
}
