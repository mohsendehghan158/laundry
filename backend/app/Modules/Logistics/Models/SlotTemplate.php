<?php

declare(strict_types=1);

namespace App\Modules\Logistics\Models;

use App\Modules\Organization\Concerns\BelongsToOrganization;
use App\Modules\Organization\Models\Branch;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A weekly pickup and delivery window of a branch, e.g. Saturdays
 * 09:00–12:00 for eight stops.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $branch_id
 * @property int $weekday 0 = Sunday … 6 = Saturday
 * @property string $starts_at H:i:s
 * @property string $ends_at H:i:s
 * @property int $capacity
 * @property bool $is_active
 */
#[Fillable(['branch_id', 'weekday', 'starts_at', 'ends_at', 'capacity', 'is_active'])]
class SlotTemplate extends Model
{
    use BelongsToOrganization;

    /** @var array<string, mixed> */
    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weekday' => 'integer',
            'capacity' => 'integer',
            'is_active' => 'boolean',
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
     * @return HasMany<Slot, $this>
     */
    public function slots(): HasMany
    {
        return $this->hasMany(Slot::class);
    }
}
