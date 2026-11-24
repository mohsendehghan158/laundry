<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Actions\UpdateRolePermissions;
use App\Modules\Identity\Http\Requests\UpdateRoleRequest;
use App\Modules\Identity\Http\Resources\RoleResource;
use App\Modules\Identity\Support\PermissionRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;

class RoleController
{
    /**
     * List roles
     */
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('roles.view');

        return RoleResource::collection(
            Role::query()->with('permissions')->withCount('users')->orderBy('id')->get(),
        );
    }

    /**
     * List permissions
     *
     * Every permission that can be granted, grouped by area.
     *
     * @response array{data: list<array{group: string, permissions: list<string>}>}
     */
    public function permissions(): JsonResponse
    {
        Gate::authorize('roles.view');

        $groups = [];

        foreach (PermissionRegistry::groups() as $group => $permissions) {
            $groups[] = ['group' => $group, 'permissions' => $permissions];
        }

        return response()->json(['data' => $groups]);
    }

    /**
     * Set the permissions of a role
     *
     * Fails with 409 `role_owner_immutable` for the owner role.
     */
    public function update(UpdateRoleRequest $request, Role $role, UpdateRolePermissions $updateRolePermissions): RoleResource
    {
        $role = $updateRolePermissions->handle($role, $request->validated('permissions'));

        return new RoleResource($role->load('permissions')->loadCount('users'));
    }
}
