<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Controllers;

use App\Modules\Billing\Actions\DeleteExpense;
use App\Modules\Billing\Actions\SaveExpense;
use App\Modules\Billing\Http\Requests\SaveExpenseRequest;
use App\Modules\Billing\Http\Resources\ExpenseResource;
use App\Modules\Billing\Models\Expense;
use App\Modules\Identity\Models\User;
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

class ExpenseController
{
    /**
     * List expenses
     *
     * Newest first, for the branches the user works in. `summary` has the
     * total and the total per category of every matching expense, not
     * just this page.
     */
    #[QueryParameter('filter[branch_id]', type: 'integer')]
    #[QueryParameter('filter[category_id]', type: 'integer')]
    #[QueryParameter('filter[from]', 'Spent on or after this local date (Y-m-d).', type: 'string')]
    #[QueryParameter('filter[to]', 'Spent on or before this local date (Y-m-d).', type: 'string')]
    #[QueryParameter('filter[q]', 'Part of the description or reference.', type: 'string')]
    #[QueryParameter('page', type: 'integer', default: 1)]
    #[QueryParameter('per_page', 'Page size, 1-100.', type: 'integer', default: 25)]
    public function index(Request $request, #[CurrentUser] User $actor): AnonymousResourceCollection
    {
        abort_unless($actor->canAny(['expenses.view', 'expenses.manage']), 403);

        $q = trim((string) $request->input('filter.q', ''));
        $filtered = fn (): Builder => Expense::query()
            ->visibleTo($actor)
            ->when($request->input('filter.branch_id'), fn (Builder $query, mixed $id) => $query->where('expenses.branch_id', (int) $id))
            ->when($request->input('filter.category_id'), fn (Builder $query, mixed $id) => $query->where('expense_category_id', (int) $id))
            ->when($request->date('filter.from'), fn (Builder $query) => $query->where('spent_on', '>=', $request->date('filter.from')?->format('Y-m-d')))
            ->when($request->date('filter.to'), fn (Builder $query) => $query->where('spent_on', '<=', $request->date('filter.to')?->format('Y-m-d')))
            ->when($q !== '', fn (Builder $query) => $query->where(fn (Builder $inner) => $inner
                ->where('description', 'ilike', '%'.addcslashes($q, '%_\\').'%')
                ->orWhere('reference', 'ilike', '%'.addcslashes($q, '%_\\').'%')));

        $expenses = $filtered()
            ->with(['branch', 'category', 'creator'])
            ->orderByDesc('spent_on')
            ->orderByDesc('id')
            ->paginate(PerPage::from($request))
            ->withQueryString();

        $byCategory = $filtered()
            ->join('expense_categories as c', 'c.id', '=', 'expenses.expense_category_id')
            ->selectRaw('c.id, c.name, sum(expenses.amount) as total')
            ->groupBy('c.id', 'c.name')
            ->orderByDesc('total')
            ->toBase()
            ->get()
            ->map(fn (object $row): array => ['id' => (int) $row->id, 'name' => (string) $row->name, 'total' => (int) $row->total])
            ->all();

        return ExpenseResource::collection($expenses)->additional(['summary' => [
            'total' => array_sum(array_column($byCategory, 'total')),
            'by_category' => $byCategory,
        ]]);
    }

    /**
     * Record an expense
     *
     * With `from_register` the cash also leaves the branch's open shift;
     * fails with 409 `shift_not_open`, `shift_cash_insufficient` or
     * `expense_register_needs_cash`.
     */
    public function store(SaveExpenseRequest $request, SaveExpense $saveExpense): JsonResponse
    {
        $this->authorizeBranch($request->actor(), (int) $request->validated('branch_id'));

        /** @var array{branch_id: int, expense_category_id: int, spent_on: string, amount: int, method: string, description: string, reference?: string|null, from_register?: bool} $data */
        $data = $request->validated();
        $expense = $saveExpense->handle($request->actor(), $data);

        return (new ExpenseResource($expense->load(['branch', 'category', 'creator'])))->response()->setStatusCode(201);
    }

    /**
     * Update an expense
     *
     * An expense paid from the register keeps its branch, amount and
     * method (409 `expense_from_register`).
     */
    public function update(SaveExpenseRequest $request, Expense $expense, SaveExpense $saveExpense): ExpenseResource
    {
        $this->authorizeBranch($request->actor(), $expense->branch_id);
        if ($request->has('branch_id')) {
            $this->authorizeBranch($request->actor(), (int) $request->validated('branch_id'));
        }

        /** @var array{branch_id?: int, expense_category_id?: int, spent_on?: string, amount?: int, method?: string, description?: string, reference?: string|null} $data */
        $data = $request->safe()->except('from_register');
        $expense = $saveExpense->handle($request->actor(), $data, $expense);

        return new ExpenseResource($expense->load(['branch', 'category', 'creator']));
    }

    /**
     * Delete an expense
     *
     * One paid from the register puts the cash back into its shift, which
     * must still be open (409 `expense_shift_closed`).
     */
    public function destroy(#[CurrentUser] User $actor, Expense $expense, DeleteExpense $deleteExpense): Response
    {
        Gate::authorize('expenses.manage');
        $this->authorizeBranch($actor, $expense->branch_id);

        $deleteExpense->handle($actor, $expense);

        return response()->noContent();
    }

    private function authorizeBranch(User $actor, int $branchId): void
    {
        abort_unless($actor->canAccessBranch(Branch::query()->findOrFail($branchId)), 404);
    }
}
