<?php

declare(strict_types=1);

namespace App\Modules\B2B\Models;

use App\Modules\Catalog\Models\PriceList;
use App\Modules\Customers\Models\Customer;
use App\Modules\Organization\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A corporate customer and its contract.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property string|null $national_id
 * @property string|null $economic_code
 * @property string|null $registration_number
 * @property string|null $postal_code
 * @property string|null $address
 * @property string|null $phone
 * @property string|null $contact_name
 * @property string|null $contact_mobile
 * @property string|null $contract_number
 * @property Carbon|null $contract_starts_on
 * @property Carbon|null $contract_ends_on
 * @property int|null $price_list_id
 * @property int|null $credit_limit
 * @property int $payment_terms_days
 * @property string|null $notes
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read PriceList|null $priceList
 */
class Company extends Model
{
    use BelongsToOrganization, SoftDeletes;

    protected $guarded = ['id'];

    /** @var array<string, mixed> */
    protected $attributes = ['payment_terms_days' => 30, 'is_active' => true];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'contract_starts_on' => 'date',
            'contract_ends_on' => 'date',
            'credit_limit' => 'integer',
            'payment_terms_days' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<PriceList, $this>
     */
    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    /**
     * @return HasMany<CompanyDepartment, $this>
     */
    public function departments(): HasMany
    {
        return $this->hasMany(CompanyDepartment::class)->orderBy('name');
    }

    /**
     * @return HasMany<Customer, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(Customer::class)->orderBy('name');
    }

    /** Orders are taken on account (paid later, up to the credit limit). */
    public function onAccount(): bool
    {
        return $this->is_active && $this->credit_limit !== null;
    }

    /** The contract's dates include today (open-ended dates count as running). */
    public function contractRunning(?Carbon $today = null): bool
    {
        $today = ($today ?? now())->toDateString();

        return ($this->contract_starts_on === null || $this->contract_starts_on->toDateString() <= $today)
            && ($this->contract_ends_on === null || $this->contract_ends_on->toDateString() >= $today);
    }
}
