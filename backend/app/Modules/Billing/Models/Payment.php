<?php

declare(strict_types=1);

namespace App\Modules\Billing\Models;

use App\Modules\Billing\Enums\PaymentKind;
use App\Modules\Billing\Enums\PaymentMethod;
use App\Modules\Identity\Models\User;
use App\Modules\Orders\Models\Order;
use App\Modules\Organization\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Money received for an order, or given back. Never changed once written;
 * a mistake is corrected with a refund.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $order_id
 * @property int $branch_id
 * @property int|null $cash_shift_id
 * @property PaymentKind $kind
 * @property PaymentMethod $method
 * @property int $amount
 * @property string|null $reference
 * @property string|null $notes
 * @property int|null $user_id
 * @property Carbon $created_at
 * @property-read Order $order
 * @property-read User|null $user
 */
class Payment extends Model
{
    use BelongsToOrganization;

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => PaymentKind::class,
            'method' => PaymentMethod::class,
            'amount' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /** +amount for a payment, −amount for a refund. */
    public function signedAmount(): int
    {
        return $this->kind === PaymentKind::Refund ? -$this->amount : $this->amount;
    }
}
