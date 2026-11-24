<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Support\PermissionRegistry;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Brings the permission and role tables in line with PermissionRegistry.
 * Safe to run on every deploy: it never touches permissions an
 * administrator has already granted to or removed from an existing role.
 */
final class SyncPermissions
{
    public function __construct(private readonly PermissionRegistrar $registrar) {}

    public function handle(): void
    {
        $names = PermissionRegistry::all();

        foreach ($names as $name) {
            Permission::findOrCreate($name);
        }

        Permission::query()->whereNotIn('name', $names)->delete();

        foreach (PermissionRegistry::defaultRoles() as $roleName => $permissions) {
            $role = Role::query()->firstOrNew([
                'name' => $roleName,
                'guard_name' => config('auth.defaults.guard'),
            ]);

            if (! $role->exists) {
                $role->save();
                $role->syncPermissions($permissions);
            }
        }

        $this->registrar->forgetCachedPermissions();
    }
}
