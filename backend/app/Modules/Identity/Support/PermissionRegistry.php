<?php

declare(strict_types=1);

namespace App\Modules\Identity\Support;

/**
 * The single list of permissions and built-in roles. The database is
 * brought in line with it by the SyncPermissions action.
 */
final class PermissionRegistry
{
    /** Holds every permission implicitly and cannot be edited. */
    public const string OWNER = 'owner';

    /**
     * @return array<string, list<string>> group => permission names
     */
    public static function groups(): array
    {
        return [
            'organization' => ['organization.update'],
            'branches' => [
                'branches.view',
                'branches.create',
                'branches.update',
                'branches.delete',
                // Work with every branch instead of only the assigned ones.
                'branches.access_all',
            ],
            'users' => ['users.view', 'users.create', 'users.update', 'users.delete'],
            'roles' => ['roles.view', 'roles.update'],
            'settings' => ['settings.view', 'settings.update'],
            'activity' => ['activity.view'],
            // Services, items, prices, upcharges, turnaround tiers, defects, colors.
            'catalog' => ['catalog.view', 'catalog.manage'],
        ];
    }

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return array_merge(...array_values(self::groups()));
    }

    /**
     * Built-in roles and the permissions they start with. Later phases
     * add their own permissions (orders, production, logistics, ...).
     *
     * @return array<string, list<string>>
     */
    public static function defaultRoles(): array
    {
        return [
            self::OWNER => [],
            'branch_manager' => [
                'branches.view',
                'users.view', 'users.create', 'users.update',
                'roles.view',
                'settings.view',
                'activity.view',
                'catalog.view',
            ],
            'cashier' => ['catalog.view'],
            'plant_operator' => [],
            'driver' => [],
            'accountant' => ['branches.view', 'branches.access_all', 'settings.view', 'catalog.view'],
            'support' => ['branches.view', 'branches.access_all', 'catalog.view'],
        ];
    }
}
