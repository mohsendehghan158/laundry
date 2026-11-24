<?php

declare(strict_types=1);

namespace App\Modules\Organization\Http\Controllers;

use App\Modules\Identity\Models\User;
use App\Modules\Organization\Actions\CreateBranch;
use App\Modules\Organization\Actions\DeleteBranch;
use App\Modules\Organization\Http\Requests\StoreBranchRequest;
use App\Modules\Organization\Http\Requests\UpdateBranchRequest;
use App\Modules\Organization\Http\Resources\BranchResource;
use App\Modules\Organization\Models\Branch;
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

class BranchController
{
    /**
     * List branches
     *
     * Only branches the current user has access to are returned.
     */
    #[QueryParameter('filter[q]', 'Search in name and code.', type: 'string')]
    #[QueryParameter('filter[type]', 'One of store, plant, hybrid.', type: 'string')]
    #[QueryParameter('filter[is_active]', type: 'boolean')]
    #[QueryParameter('sort', 'One of name, code, created_at; prefix with "-" for descending.', type: 'string', default: 'name')]
    #[QueryParameter('page', type: 'integer', default: 1)]
    #[QueryParameter('per_page', 'Page size, 1-100.', type: 'integer', default: 25)]
    public function index(Request $request, #[CurrentUser] User $actor): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Branch::class);

        $branches = QueryBuilder::for(Branch::query()->visibleTo($actor))
            ->allowedFilters(
                AllowedFilter::callback('q', function (Builder $query, mixed $value): void {
                    $term = '%'.(is_array($value) ? implode(',', $value) : $value).'%';
                    $query->where(fn (Builder $q) => $q->whereLike('name', $term)->orWhereLike('code', $term));
                }),
                AllowedFilter::exact('type'),
                AllowedFilter::exact('is_active'),
            )
            ->allowedSorts('name', 'code', 'created_at')
            ->defaultSort('name')
            ->withCount('users')
            ->paginate(PerPage::from($request))
            ->withQueryString();

        return BranchResource::collection($branches);
    }

    /**
     * Create a branch
     */
    public function store(StoreBranchRequest $request, CreateBranch $createBranch): JsonResponse
    {
        $branch = $createBranch->handle($request->actor(), $request->validated());

        return (new BranchResource($branch))->response()->setStatusCode(201);
    }

    /**
     * Show a branch
     */
    public function show(Branch $branch): BranchResource
    {
        Gate::authorize('view', $branch);

        return new BranchResource($branch->loadCount('users'));
    }

    /**
     * Update a branch
     */
    public function update(UpdateBranchRequest $request, Branch $branch): BranchResource
    {
        $branch->update($request->validated());

        return new BranchResource($branch);
    }

    /**
     * Delete a branch
     *
     * Fails with 409 `branch_last` when it is the only branch left.
     */
    public function destroy(Branch $branch, DeleteBranch $deleteBranch): Response
    {
        Gate::authorize('delete', $branch);

        $deleteBranch->handle($branch);

        return response()->noContent();
    }
}
