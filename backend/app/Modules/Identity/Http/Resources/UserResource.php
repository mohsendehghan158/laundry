<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Resources;

use App\Modules\Identity\Models\User;
use App\Modules\Organization\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email,
            'mobile' => $this->mobile,
            'is_active' => $this->is_active,
            'locale' => $this->locale,
            'last_login_at' => $this->last_login_at,
            /** @var list<string> Role names. */
            'roles' => $this->roles->pluck('name')->values()->all(),
            /** @var list<array{id: int, name: string, code: string}> Assigned branches. */
            'branches' => $this->branches
                ->map(fn (Branch $branch): array => [
                    'id' => $branch->id,
                    'name' => $branch->name,
                    'code' => $branch->code,
                ])
                ->values()
                ->all(),
            'created_at' => $this->created_at,
        ];
    }
}
