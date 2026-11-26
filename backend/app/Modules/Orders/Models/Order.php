<?php

declare(strict_types=1);

namespace App\Modules\Orders\Models;

use App\Modules\Billing\Models\Payment;
use App\Modules\Billing\Models\PromoCode;
use App\Modules\Customers\Models\Customer;
use App\Modules\Identity\Models\User;
use App\Modules\Orders\Database\Factories\OrderFactory;
use App\Modules\Orders\Enums\DiscountType;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Policies\OrderPolicy;
use App\Modules\Organization\Concerns\BelongsToOrganization;
use App\Modules\Organization\Models\Branch;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $branch_id
 * @property int $customer_id
 * @property string $public_id
 * @property string $ticket_number
 * @property OrderStatus $status
 * @property PaymentStatus $payment_status
 * @property int|null $turnaround_tier_id
 * @property string|null $turnaround_name
 * @property int $surcharge_percent
 * @property Carbon $received_at
 * @property Carbon $due_at
 * @property Carbon|null $ready_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $cancelled_at
 * @property string|null $cancel_reason
 * @property string|null $rack_location
 * @property string|null $notes
 * @property int $subtotal
 * @property DiscountType|null $discount_type
 * @property string $discount_value
 * @property int $discount_amount
 * @property string|null $discount_reason
 * @property int|null $promo_code_id
 * @property int $tax_amount
 * @property int $total
 * @property int $paid_amount
 * @property string|null $idempotency_key
 * @property int|null $created_by
 * @property int $version
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Branch $branch
 * @property-read Customer $customer
 * @property-read User|null $creator
 * @property-read PromoCode|null $promoCode
 */
#[UseFactory(OrderFactory::class)]
#[UsePolicy(OrderPolicy::class)]
class Order extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    use HasUlids;

    protected $guarded = ['id'];

    /**
     * The ULID goes into public_id; the primary key stays numeric.
     *
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'payment_status' => PaymentStatus::class,
            'discount_type' => DiscountType::class,
            'received_at' => 'datetime',
            'due_at' => 'datetime',
            'ready_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'surcharge_percent' => 'integer',
            'subtotal' => 'integer',
            'discount_amount' => 'integer',
            'tax_amount' => 'integer',
            'total' => 'integer',
            'paid_amount' => 'integer',
            'version' => 'integer',
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
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    /**
     * @return HasMany<OrderLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(OrderLine::class)->orderBy('position');
    }

    /**
     * @return HasMany<Piece, $this>
     */
    public function pieces(): HasMany
    {
        return $this->hasMany(Piece::class)->orderBy('number');
    }

    /**
     * @return HasMany<OrderStatusChange, $this>
     */
    public function history(): HasMany
    {
        return $this->hasMany(OrderStatusChange::class)->orderBy('id');
    }

    /**
     * @return BelongsTo<PromoCode, $this>
     */
    public function promoCode(): BelongsTo
    {
        return $this->belongsTo(PromoCode::class)->withTrashed();
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderBy('id');
    }

    public function balance(): int
    {
        return max(0, $this->total - $this->paid_amount);
    }

    /**
     * Orders in branches the user may work with.
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
