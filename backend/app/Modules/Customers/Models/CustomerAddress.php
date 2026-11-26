<?php

declare(strict_types=1);

namespace App\Modules\Customers\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Scoped through its customer, which belongs to an organization.
 *
 * @property int $id
 * @property int $customer_id
 * @property string|null $title
 * @property string $address
 * @property string|null $latitude
 * @property string|null $longitude
 * @property bool $is_default
 * @property-read Customer|null $customer archived customers are not loaded
 */
#[Fillable(['title', 'address', 'latitude', 'longitude', 'is_default'])]
class CustomerAddress extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
