<?php

declare(strict_types=1);

namespace App\Modules\Billing\Models;

use App\Modules\Billing\Enums\PaymentMethod;
use App\Modules\Identity\Models\User;
use App\Modules\Organization\Concerns\BelongsToOrganization;
use App\Modules\Organization\Models\Branch;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Money a branch spent, in whole rials.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $branch_id
 * @property int $expense_category_id
 * @property Carbon $spent_on
 * @property int $amount
 * @property PaymentMethod $method
 * @property string $description
 * @property string|null $reference
 * @property int|null $cash_movement_id
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Branch $branch
 * @property-read ExpenseCategory $category
 * @property-read CashMovement|null $cashMovement
 * @property-read User|null $creator
 */
class Expense extends Model
{
    use BelongsToOrganization, SoftDeletes;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'spent_on' => 'date',
            'amount' => 'integer',
            'method' => PaymentMethod::class,
        ];
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class)->withTrashed();
    }

    /**
     * @return BelongsTo<ExpenseCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id')->withTrashed();
    }

    /**
     * @return BelongsTo<CashMovement, $this>
     */
    public function cashMovement(): BelongsTo
    {
        return $this->belongsTo(CashMovement::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    /**
     * Expenses of branches the user may work with.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        if ($user->hasAllBranchAccess()) {
            return;
        }

        $query->whereIn('branch_id', $user->branches()->select('branches.id'));
    }
}
