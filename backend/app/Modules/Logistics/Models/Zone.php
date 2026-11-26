<?php

declare(strict_types=1);

namespace App\Modules\Logistics\Models;

use App\Modules\Logistics\Database\Factories\ZoneFactory;
use App\Modules\Logistics\Support\Polygon;
use App\Modules\Organization\Concerns\BelongsToOrganization;
use App\Modules\Organization\Models\Branch;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * An area served by pickup and delivery, and the branch that serves it.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $branch_id
 * @property string $name
 * @property list<array{0: float, 1: float}> $polygon
 * @property string $min_latitude
 * @property string $max_latitude
 * @property string $min_longitude
 * @property string $max_longitude
 * @property int $delivery_fee
 * @property int|null $free_delivery_over
 * @property int|null $min_order
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Branch $branch
 */
#[Fillable(['branch_id', 'name', 'delivery_fee', 'free_delivery_over', 'min_order', 'is_active'])]
#[UseFactory(ZoneFactory::class)]
class Zone extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<ZoneFactory> */
    use HasFactory;

    use SoftDeletes;

    /** @var array<string, mixed> */
    protected $attributes = [
        'delivery_fee' => 0,
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'polygon' => 'array',
            'delivery_fee' => 'integer',
            'free_delivery_over' => 'integer',
            'min_order' => 'integer',
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

    /** Sets the corners together with the bounding box used for lookups. */
    public function setShape(Polygon $polygon): static
    {
        $this->polygon = $polygon->points;
        $this->forceFill($polygon->bounds());

        return $this;
    }

    public function shape(): Polygon
    {
        return Polygon::from($this->polygon);
    }

    /** What delivery costs for an order of this many rials. */
    public function deliveryFeeFor(int $orderTotal): int
    {
        if ($this->free_delivery_over !== null && $orderTotal >= $this->free_delivery_over) {
            return 0;
        }

        return $this->delivery_fee;
    }
}
