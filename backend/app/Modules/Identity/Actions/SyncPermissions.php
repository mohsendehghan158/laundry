<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Support\PermissionRegistry;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Brings the permission and role tables in line with PermissionRegistry.
 * Safe to run on every deploy: permissions that already existed are never
 * granted or revoked again, so an administrator's changes to a role stay.
 * A permission introduced by a new release is granted once to the
 * built-in roles that have it by default.
 */
final class SyncPermissions
{
    public function __construct(private readonly PermissionRegistrar $registrar) {}

    public function handle(): void
    {
        $names = PermissionRegistry::all();
        $existing = Permission::query()->pluck('name')->all();
        $introduced = array_values(array_diff($names, $existing));

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

                continue;
            }

            $newDefaults = array_values(array_intersect($permissions, $introduced));

            if ($newDefaults !== []) {
                $role->givePermissionTo($newDefaults);
            }
        }

        $this->registrar->forgetCachedPermissions();
    }
}
