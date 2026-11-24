<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Resources;

use App\Modules\Identity\Models\User;
use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The signed-in user together with everything the interface needs to
 * decide what to show: permissions and accessible branches.
 *
 * @mixin User
 */
class AuthUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;
        $organization = $user->organization()->firstOrFail();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email,
            'mobile' => $this->mobile,
            'locale' => $this->locale,
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                /** @var 'toman'|'rial' Unit amounts are shown and typed in; they are always stored in rials. */
                'currency_unit' => app(Settings::class)->get('general.currency_unit'),
            ],
            /** @var list<string> Role names. */
            'roles' => $this->roles->pluck('name')->values()->all(),
            /** @var list<string> Every permission the user effectively holds. */
            'permissions' => $this->permissionNames(),
            /** @var list<array{id: int, name: string, code: string, type: 'store'|'plant'|'hybrid'}> Active branches the user can work in. */
            'branches' => Branch::query()
                ->visibleTo($user)
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
                ->map(fn (Branch $branch): array => [
                    'id' => $branch->id,
                    'name' => $branch->name,
                    'code' => $branch->code,
                    'type' => $branch->type->value,
                ])
                ->all(),
        ];
    }
}
