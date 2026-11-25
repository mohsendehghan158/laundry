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
            // manage = create and edit.
            'customers' => ['customers.view', 'customers.manage', 'customers.delete'],
            // update = change lines and status; discount = up to the configured cap.
            // handover_unpaid = release an order before it is paid in full.
            'orders' => ['orders.view', 'orders.create', 'orders.update', 'orders.cancel', 'orders.discount', 'orders.discount_unlimited', 'orders.handover', 'orders.handover_unpaid'],
            // shifts.operate = open, close and use the register; shifts.view = review past shifts.
            'billing' => ['payments.create', 'payments.refund', 'shifts.operate', 'shifts.view'],
            'promotions' => ['promotions.manage'],
            // manage = edit message texts and resend; view = the send log.
            'notifications' => ['notifications.manage', 'notifications.view'],
            'production' => ['production.view', 'production.scan', 'production.manage', 'production.transfer', 'production.outsource'],
            'claims' => ['claims.view', 'claims.manage'],
            'reports' => ['reports.view', 'reports.production'],
            // manage = service zones, weekly windows and individual slots.
            'logistics' => ['logistics.view', 'logistics.manage'],
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
                'customers.view', 'customers.manage', 'customers.delete',
                'orders.view', 'orders.create', 'orders.update', 'orders.cancel', 'orders.discount', 'orders.handover', 'orders.handover_unpaid',
                'payments.create', 'payments.refund', 'shifts.operate', 'shifts.view',
                'notifications.view',
                'production.view', 'production.scan', 'production.manage', 'production.transfer', 'production.outsource',
                'claims.view', 'claims.manage',
                'reports.view', 'reports.production',
                'logistics.view', 'logistics.manage',
            ],
            'cashier' => ['catalog.view', 'customers.view', 'customers.manage', 'orders.view', 'orders.create', 'orders.update', 'orders.discount', 'orders.handover', 'payments.create', 'shifts.operate', 'production.view', 'production.transfer', 'claims.view'],
            'plant_operator' => ['catalog.view', 'orders.view', 'orders.update', 'production.view', 'production.scan', 'production.transfer', 'production.outsource', 'claims.view', 'reports.production'],
            'driver' => ['customers.view', 'logistics.view'],
            'accountant' => ['branches.view', 'branches.access_all', 'settings.view', 'catalog.view', 'customers.view', 'orders.view', 'shifts.view', 'production.view', 'claims.view', 'reports.view', 'reports.production'],
            'support' => ['branches.view', 'branches.access_all', 'catalog.view', 'customers.view', 'customers.manage', 'customers.delete', 'orders.view', 'orders.create', 'notifications.view', 'production.view', 'claims.view', 'logistics.view'],
        ];
    }
}
