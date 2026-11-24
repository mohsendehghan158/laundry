<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Actions\Concerns\GuardsOwners;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Support\PermissionRegistry;
use App\Modules\Organization\Models\Branch;
use App\Support\Exceptions\ApiException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

final class UpdateUser
{
    use GuardsOwners;

    /**
     * @param  array<string, mixed>  $data  validated by UpdateUserRequest
     */
    public function handle(User $actor, User $user, array $data): User
    {
        $deactivates = ($data['is_active'] ?? true) === false && $user->is_active;
        $dropsOwner = isset($data['roles'])
            && $user->isOwner()
            && ! in_array(PermissionRegistry::OWNER, $data['roles'], true);

        if ($deactivates && $user->is($actor)) {
            throw ApiException::conflict('user_cannot_deactivate_self');
        }

        if (($deactivates || $dropsOwner) && $user->isOwner()) {
            $this->ensureAnotherActiveOwnerExists($user);
        }

        return DB::transaction(function () use ($actor, $user, $data): User {
            $attributes = Arr::except($data, ['roles', 'branch_ids', 'password']);

            if (filled($data['password'] ?? null)) {
                $attributes['password'] = $data['password'];
            }

            $user->update($attributes);

            if (isset($data['roles'])) {
                $this->changeRoles($user, $data['roles']);
            }

            if (isset($data['branch_ids'])) {
                $user->branches()->sync([...$this->branchesHiddenFrom($actor, $user), ...$data['branch_ids']]);
            }

            return $user;
        });
    }

    /**
     * Roles are not attributes, so the model's own change log misses
     * them; a change of access is recorded explicitly.
     *
     * @param  list<string>  $roles
     */
    private function changeRoles(User $user, array $roles): void
    {
        $before = $user->roles->pluck('name')->sort()->values()->all();
        $after = collect($roles)->sort()->values()->all();

        if ($before === $after) {
            return;
        }

        $user->syncRoles($after);

        activity('user')
            ->performedOn($user)
            ->event('roles_updated')
            ->withChanges(['attributes' => ['roles' => $after], 'old' => ['roles' => $before]])
            ->log('roles_updated');
    }

    /**
     * Assignments the actor cannot see must survive the update: a branch
     * manager editing a colleague only decides about their own branches.
     *
     * @return list<int>
     */
    private function branchesHiddenFrom(User $actor, User $user): array
    {
        if ($actor->hasAllBranchAccess()) {
            return [];
        }

        $visible = Branch::query()->visibleTo($actor)->pluck('id');

        return array_values(
            $user->branches()
                ->whereKeyNot($visible)
                ->get()
                ->map(fn (Branch $branch): int => $branch->id)
                ->all(),
        );
    }
}
