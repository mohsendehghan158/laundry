<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Actions\CreateUser;
use App\Modules\Identity\Actions\DeleteUser;
use App\Modules\Identity\Actions\UpdateUser;
use App\Modules\Identity\Http\Requests\StoreUserRequest;
use App\Modules\Identity\Http\Requests\UpdateUserRequest;
use App\Modules\Identity\Http\Resources\UserResource;
use App\Modules\Identity\Models\User;
use App\Support\Http\PerPage;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class UserController
{
    /**
     * List users
     *
     * Staff of the organization. Users limited to their own branches only
     * see colleagues who share a branch with them.
     */
    #[QueryParameter('filter[q]', 'Search in name, username and mobile.', type: 'string')]
    #[QueryParameter('filter[role]', 'Role name.', type: 'string')]
    #[QueryParameter('filter[branch_id]', type: 'integer')]
    #[QueryParameter('filter[is_active]', type: 'boolean')]
    #[QueryParameter('sort', 'One of name, username, created_at, last_login_at; prefix with "-" for descending.', type: 'string', default: 'name')]
    #[QueryParameter('page', type: 'integer', default: 1)]
    #[QueryParameter('per_page', 'Page size, 1-100.', type: 'integer', default: 25)]
    public function index(Request $request, #[CurrentUser] User $actor): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', User::class);

        $users = QueryBuilder::for(User::query()->visibleTo($actor))
            ->allowedFilters(
                AllowedFilter::callback('q', function (Builder $query, mixed $value): void {
                    $term = '%'.(is_array($value) ? implode(',', $value) : $value).'%';
                    $query->where(fn (Builder $q) => $q
                        ->whereLike('name', $term)
                        ->orWhereLike('username', $term)
                        ->orWhereLike('mobile', $term));
                }),
                AllowedFilter::callback('role', fn (Builder $query, mixed $value) => $query
                    ->whereHas('roles', fn (Builder $roles) => $roles->whereIn('name', (array) $value))),
                AllowedFilter::callback('branch_id', fn (Builder $query, mixed $value) => $query
                    ->whereHas('branches', fn (Builder $branches) => $branches->whereKey((array) $value))),
                AllowedFilter::exact('is_active'),
            )
            ->allowedSorts('name', 'username', 'created_at', 'last_login_at')
            ->defaultSort('name')
            ->with(['roles', 'branches'])
            ->paginate(PerPage::from($request))
            ->withQueryString();

        return UserResource::collection($users);
    }

    /**
     * Create a user
     */
    public function store(StoreUserRequest $request, CreateUser $createUser): JsonResponse
    {
        $user = $createUser->handle($request->validated());

        return (new UserResource($user))->response()->setStatusCode(201);
    }

    /**
     * Show a user
     */
    public function show(User $user): UserResource
    {
        Gate::authorize('view', $user);

        return new UserResource($user);
    }

    /**
     * Update a user
     *
     * Fails with 409 `user_cannot_deactivate_self` or `user_last_owner`
     * when the change would lock the organization out.
     */
    public function update(UpdateUserRequest $request, User $user, UpdateUser $updateUser): UserResource
    {
        return new UserResource($updateUser->handle($request->actor(), $user, $request->validated()));
    }

    /**
     * Delete a user
     *
     * Fails with 409 `user_cannot_delete_self` or `user_last_owner`.
     */
    public function destroy(User $user, #[CurrentUser] User $actor, DeleteUser $deleteUser): Response
    {
        Gate::authorize('delete', $user);

        $deleteUser->handle($actor, $user);

        return response()->noContent();
    }
}
