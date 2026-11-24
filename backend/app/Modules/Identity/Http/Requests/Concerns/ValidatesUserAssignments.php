<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests\Concerns;

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Support\PermissionRegistry;
use App\Modules\Organization\Models\Branch;
use App\Support\Http\Middleware\SetLocale;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

/**
 * Rules shared by creating and updating a user. What may be assigned
 * depends on who is asking: only an owner can make another owner, and
 * nobody can hand out a branch they cannot access themselves.
 */
trait ValidatesUserAssignments
{
    abstract public function actor(): User;

    /**
     * @return array<string, list<mixed>>
     */
    protected function assignmentRules(): array
    {
        $actor = $this->actor();

        $roles = Role::query()
            ->when(! $actor->isOwner(), fn ($query) => $query->where('name', '!=', PermissionRegistry::OWNER))
            ->pluck('name');

        $branchIds = Branch::query()->visibleTo($actor)->pluck('id');

        return [
            'roles.*' => ['string', 'distinct', Rule::in($roles)],
            'branch_ids' => ['array'],
            'branch_ids.*' => ['integer', 'distinct', Rule::in($branchIds)],
            'is_active' => ['boolean'],
            'locale' => [Rule::in(SetLocale::SUPPORTED)],
        ];
    }
}
