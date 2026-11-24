<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Resources;

use App\Modules\Identity\Support\PermissionRegistry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\Permission\Models\Role;

/**
 * @mixin Role
 */
class RoleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isOwner = $this->name === PermissionRegistry::OWNER;

        return [
            'id' => $this->id,
            'name' => $this->name,
            /** The owner role always holds every permission and cannot be edited. */
            'is_owner' => $isOwner,
            /** @var list<string> */
            'permissions' => $isOwner
                ? PermissionRegistry::all()
                : $this->permissions->pluck('name')->sort()->values()->all(),
            /** Number of users holding the role. */
            'users_count' => $this->whenCounted('users'),
        ];
    }
}
