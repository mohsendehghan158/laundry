<?php

declare(strict_types=1);

namespace App\Modules\Billing\Models;

use App\Modules\Billing\Enums\CashMovementType;
use App\Modules\Billing\Enums\PaymentKind;
use App\Modules\Billing\Enums\PaymentMethod;
use App\Modules\Billing\Enums\ShiftStatus;
use App\Modules\Identity\Models\User;
use App\Modules\Organization\Concerns\BelongsToOrganization;
use App\Modules\Organization\Models\Branch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $branch_id
 * @property ShiftStatus $status
 * @property int|null $opened_by
 * @property Carbon $opened_at
 * @property int $opening_float
 * @property int|null $closed_by
 * @property Carbon|null $closed_at
 * @property int|null $expected_cash
 * @property int|null $counted_cash
 * @property int|null $difference
 * @property string|null $notes
 * @property-read Branch $branch
 * @property-read User|null $opener
 * @property-read User|null $closer
 */
class CashShift extends Model
{
    use BelongsToOrganization;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ShiftStatus::class,
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'opening_float' => 'integer',
            'expected_cash' => 'integer',
            'counted_cash' => 'integer',
            'difference' => 'integer',
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
     * @return BelongsTo<User, $this>
     */
    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by')->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by')->withTrashed();
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderBy('id');
    }

    /**
     * @return HasMany<CashMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(CashMovement::class)->orderBy('id');
    }

    /**
     * Money taken during the shift, by method (refunds subtracted), and
     * the cash that should be in the drawer.
     *
     * @return array{by_method: array<string, int>, payments: int, refunds: int, cash_in: int, cash_out: int, expected_cash: int}
     */
    public function totals(): array
    {
        $byMethod = array_fill_keys(array_map(fn (PaymentMethod $m): string => $m->value, PaymentMethod::cases()), 0);
        $payments = 0;
        $refunds = 0;

        foreach ($this->payments()->get(['kind', 'method', 'amount']) as $payment) {
            $sign = $payment->kind === PaymentKind::Refund ? -1 : 1;
            $byMethod[$payment->method->value] += $sign * $payment->amount;
            $payment->kind === PaymentKind::Refund ? $refunds += $payment->amount : $payments += $payment->amount;
        }

        $cashIn = (int) $this->movements()->where('type', CashMovementType::In)->sum('amount');
        $cashOut = (int) $this->movements()->where('type', CashMovementType::Out)->sum('amount');

        return [
            'by_method' => $byMethod,
            'payments' => $payments,
            'refunds' => $refunds,
            'cash_in' => $cashIn,
            'cash_out' => $cashOut,
            'expected_cash' => $this->opening_float + $byMethod[PaymentMethod::Cash->value] + $cashIn - $cashOut,
        ];
    }
}
