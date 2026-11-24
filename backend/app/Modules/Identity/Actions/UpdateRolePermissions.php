<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Support\PermissionRegistry;
use App\Support\Exceptions\ApiException;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

final class UpdateRolePermissions
{
    /**
     * @param  list<string>  $permissions
     */
    public function handle(Role $role, array $permissions): Role
    {
        if ($role->name === PermissionRegistry::OWNER) {
            throw ApiException::conflict('role_owner_immutable');
        }

        $before = $role->permissions->pluck('name')->sort()->values()->all();
        $after = collect($permissions)->sort()->values()->all();

        if ($before === $after) {
            return $role;
        }

        return DB::transaction(function () use ($role, $before, $after): Role {
            $role->syncPermissions($after);

            activity('role')
                ->performedOn($role)
                ->event('updated')
                ->withChanges(['attributes' => ['permissions' => $after], 'old' => ['permissions' => $before]])
                ->log('updated');

            return $role;
        });
    }
}
