<?php

declare(strict_types=1);

namespace App\Modules\Billing\Models;

use App\Modules\Orders\Enums\DiscountType;
use App\Modules\Organization\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property int $organization_id
 * @property string $code
 * @property string|null $description
 * @property DiscountType $type
 * @property int $value rials for "amount", hundredths of a percent for "percent"
 * @property int|null $max_discount
 * @property int $min_order
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property int|null $usage_limit
 * @property int|null $per_customer_limit
 * @property bool $first_order_only
 * @property bool $is_active
 */
#[Fillable(['code', 'description', 'type', 'value', 'max_discount', 'min_order', 'starts_at', 'ends_at', 'usage_limit', 'per_customer_limit', 'first_order_only', 'is_active'])]
class PromoCode extends Model
{
    use BelongsToOrganization, LogsActivity, SoftDeletes;

    /** @var array<string, mixed> */
    protected $attributes = [
        'min_order' => 0,
        'first_order_only' => false,
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => DiscountType::class,
            'value' => 'integer',
            'max_discount' => 'integer',
            'min_order' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'usage_limit' => 'integer',
            'per_customer_limit' => 'integer',
            'first_order_only' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('promo')
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
