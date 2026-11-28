<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Controllers;

use App\Modules\Billing\Http\Requests\SaveExpenseCategoryRequest;
use App\Modules\Billing\Http\Resources\ExpenseCategoryResource;
use App\Modules\Billing\Models\ExpenseCategory;
use App\Modules\Identity\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ExpenseCategoryController
{
    /**
     * List expense categories
     *
     * Inactive ones included. A new organization starts with a default set.
     */
    public function index(#[CurrentUser] User $actor): AnonymousResourceCollection
    {
        abort_unless($actor->canAny(['expenses.view', 'expenses.manage', 'expenses.categories', 'reports.view']), 403);

        ExpenseCategory::ensureDefaults();

        return ExpenseCategoryResource::collection(ExpenseCategory::query()->orderBy('name')->get());
    }

    /**
     * Create an expense category
     */
    public function store(SaveExpenseCategoryRequest $request): JsonResponse
    {
        $category = ExpenseCategory::query()->create($request->validated());

        return (new ExpenseCategoryResource($category->refresh()))->response()->setStatusCode(201);
    }

    /**
     * Update an expense category
     */
    public function update(SaveExpenseCategoryRequest $request, ExpenseCategory $expenseCategory): ExpenseCategoryResource
    {
        $expenseCategory->update($request->validated());

        return new ExpenseCategoryResource($expenseCategory);
    }

    /**
     * Delete an expense category
     *
     * Expenses already recorded keep it.
     */
    public function destroy(ExpenseCategory $expenseCategory): Response
    {
        Gate::authorize('expenses.categories');

        $expenseCategory->delete();

        return response()->noContent();
    }
}
