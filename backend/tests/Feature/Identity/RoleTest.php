<?php

declare(strict_types=1);

use App\Modules\Audit\Models\Activity;
use App\Modules\Identity\Actions\SyncPermissions;
use App\Modules\Identity\Support\PermissionRegistry;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

it('lists the built-in roles with their permissions and member counts', function () {
    $owner = $this->actingAsOwner();
    $this->userWithRole('cashier', $owner->organization);
    // Members of another organization are not counted.
    $this->userWithRole('cashier');

    $response = $this->getJson('/api/v1/roles')->assertOk();

    expect($response->json('data.*.name'))->toBe(array_keys(PermissionRegistry::defaultRoles()));

    $roles = collect($response->json('data'))->keyBy('name');
    expect($roles['owner']['is_owner'])->toBeTrue()
        ->and($roles['owner']['permissions'])->toBe(PermissionRegistry::all())
        ->and($roles['owner']['users_count'])->toBe(1)
        ->and($roles['cashier']['users_count'])->toBe(1)
        ->and($roles['branch_manager']['permissions'])->toContain('users.create')
        ->and($roles['branch_manager']['permissions'])->not->toContain('roles.update');
});

it('lists every permission grouped by area', function () {
    $this->actingAsOwner();

    $groups = $this->getJson('/api/v1/permissions')->assertOk()->json('data');

    expect(array_column($groups, 'group'))->toBe(array_keys(PermissionRegistry::groups()))
        ->and(array_merge(...array_column($groups, 'permissions')))->toBe(PermissionRegistry::all());
});

it('replaces the permissions of a role and takes effect immediately', function () {
    $owner = $this->actingAsOwner();
    $cashier = $this->userWithRole('cashier', $owner->organization);
    $role = Role::findByName('cashier');

    $this->putJson("/api/v1/roles/{$role->id}", ['permissions' => ['branches.view', 'users.view']])
        ->assertOk()
        ->assertJsonPath('data.permissions', ['branches.view', 'users.view']);

    expect($cashier->fresh()->can('users.view'))->toBeTrue();

    $this->putJson("/api/v1/roles/{$role->id}", ['permissions' => []])->assertOk();
    expect($cashier->fresh()->can('users.view'))->toBeFalse();

    $entry = Activity::query()->where('log_name', 'role')->oldest('id')->firstOrFail();
    expect($entry->attribute_changes->all())->toBe([
        'attributes' => ['permissions' => ['branches.view', 'users.view']],
        'old' => ['permissions' => collect(PermissionRegistry::defaultRoles()['cashier'])->sort()->values()->all()],
    ]);
});

it('rejects unknown permissions', function () {
    $this->actingAsOwner();
    $role = Role::findByName('cashier');

    $this->putJson("/api/v1/roles/{$role->id}", ['permissions' => ['orders.fly']])
        ->assertUnprocessable()->assertJsonValidationErrors('permissions.0');
    $this->putJson("/api/v1/roles/{$role->id}", [])
        ->assertUnprocessable()->assertJsonValidationErrors('permissions');
});

it('keeps the owner role fixed', function () {
    $this->actingAsOwner();
    $role = Role::findByName(PermissionRegistry::OWNER);

    $this->putJson("/api/v1/roles/{$role->id}", ['permissions' => []])
        ->assertConflict()
        ->assertExactJson(['message' => 'مجوزهای نقش مالک قابل تغییر نیست.', 'code' => 'role_owner_immutable']);
});

it('requires permission to view or edit roles', function () {
    $role = Role::findByName('cashier');

    $this->actingAs($this->userWithRole('cashier'));
    $this->getJson('/api/v1/roles')->assertForbidden();
    $this->getJson('/api/v1/permissions')->assertForbidden();

    $this->actingAs($this->userWithRole('branch_manager'));
    $this->getJson('/api/v1/roles')->assertOk();
    $this->putJson("/api/v1/roles/{$role->id}", ['permissions' => []])->assertForbidden();
});

it('does not let someone grant a permission they do not hold', function () {
    $manager = $this->userWithRole('branch_manager');
    $manager->givePermissionTo('roles.update');
    $this->actingAs($manager);
    $role = Role::findByName('cashier');

    $this->putJson("/api/v1/roles/{$role->id}", ['permissions' => ['users.view', 'users.delete']])
        ->assertUnprocessable()
        ->assertJsonPath('errors.permissions.0', 'نمی‌توانید مجوزی بدهید که خودتان ندارید.');

    $this->putJson("/api/v1/roles/{$role->id}", ['permissions' => ['users.view']])->assertOk();
});

it('gives owners permissions that are added later, without a role edit', function () {
    $owner = $this->owner();
    Permission::findOrCreate('orders.create');

    expect($owner->can('orders.create'))->toBeTrue()
        ->and($this->userWithRole('cashier')->can('orders.create'))->toBeFalse();
});

it('syncs permissions without undoing an administrator\'s changes', function () {
    $role = Role::findByName('branch_manager');
    $role->syncPermissions(['branches.view']);
    Permission::findOrCreate('legacy.permission');

    app(SyncPermissions::class)->handle();

    expect($role->fresh()->permissions->pluck('name')->all())->toBe(['branches.view'])
        ->and(Permission::query()->where('name', 'legacy.permission')->exists())->toBeFalse()
        ->and(Permission::query()->count())->toBe(count(PermissionRegistry::all()));
});

it('grants a newly released permission to the built-in roles that have it by default, once', function () {
    $manager = Role::findByName('branch_manager');
    $accountant = Role::findByName('accountant');
    // Simulate an install made before "branches.view" existed: the
    // permission is gone and the administrator also trimmed the accountant.
    Permission::findByName('branches.view')->delete();
    $accountant->syncPermissions(['settings.view']);

    app(SyncPermissions::class)->handle();

    expect($manager->fresh()->hasPermissionTo('branches.view'))->toBeTrue()
        ->and($accountant->fresh()->hasPermissionTo('branches.view'))->toBeTrue()
        ->and($accountant->fresh()->hasPermissionTo('branches.access_all'))->toBeFalse()
        ->and(Role::findByName('cashier')->hasPermissionTo('branches.view'))->toBeFalse();

    // Removing it again is respected by the next sync.
    $manager->revokePermissionTo('branches.view');
    app(SyncPermissions::class)->handle();

    expect($manager->fresh()->hasPermissionTo('branches.view'))->toBeFalse();
});
