<?php

declare(strict_types=1);

namespace App\Modules\Billing\Models;

use App\Modules\Billing\Enums\InvoiceStatus;
use App\Modules\Customers\Models\Customer;
use App\Modules\Identity\Models\User;
use App\Modules\Orders\Models\Order;
use App\Modules\Organization\Concerns\BelongsToOrganization;
use App\Modules\Organization\Models\Branch;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An official VAT invoice: a frozen copy of one or more orders with the
 * seller's and buyer's details. Never edited; a mistake is voided and a
 * new invoice issued.
 *
 * @phpstan-type Party array{type?: string, name: string, national_id: string|null, economic_code: string|null, registration_number: string|null, postal_code: string|null, address: string|null, phone: string|null}
 *
 * @property int $id
 * @property int $organization_id
 * @property int $branch_id
 * @property int $customer_id
 * @property int|null $company_id set when every order is charged to one company
 * @property int $number
 * @property InvoiceStatus $status
 * @property Carbon $issued_at
 * @property Party $seller
 * @property Party $buyer
 * @property string $vat_percent
 * @property int $subtotal
 * @property int $discount_amount
 * @property int $tax_amount
 * @property int $total
 * @property string|null $notes
 * @property int|null $created_by
 * @property Carbon|null $voided_at
 * @property int|null $voided_by
 * @property string|null $void_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Branch $branch
 * @property-read Customer $customer
 * @property-read User|null $creator
 * @property-read User|null $voider
 */
class Invoice extends Model
{
    use BelongsToOrganization;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'status' => InvoiceStatus::class,
            'issued_at' => 'datetime',
            'seller' => 'array',
            'buyer' => 'array',
            'subtotal' => 'integer',
            'discount_amount' => 'integer',
            'tax_amount' => 'integer',
            'total' => 'integer',
            'voided_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by')->withTrashed();
    }

    /**
     * @return HasMany<InvoiceLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class)->orderBy('position');
    }

    /**
     * @return BelongsToMany<Order, $this>
     */
    public function orders(): BelongsToMany
    {
        return $this->belongsToMany(Order::class, 'invoice_orders')->orderBy('orders.id');
    }

    /**
     * Invoices of branches the user may work with.
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
