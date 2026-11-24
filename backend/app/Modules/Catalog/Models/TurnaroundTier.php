<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Concerns\CatalogEntry;
use App\Modules\Catalog\Database\Factories\TurnaroundTierFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property int $duration_hours
 * @property int $surcharge_percent added to the line prices, e.g. 50 for +50%
 * @property bool $is_default
 * @property int $sort_order
 * @property bool $is_active
 */
#[Fillable(['name', 'duration_hours', 'surcharge_percent', 'is_default', 'sort_order', 'is_active'])]
#[UseFactory(TurnaroundTierFactory::class)]
class TurnaroundTier extends Model
{
    use CatalogEntry;

    /** @use HasFactory<TurnaroundTierFactory> */
    use HasFactory;

    use SoftDeletes;

    /** @var array<string, mixed> */
    protected $attributes = [
        'sort_order' => 0,
        'is_active' => true,
        'surcharge_percent' => 0,
        'is_default' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'duration_hours' => 'integer',
            'surcharge_percent' => 'integer',
            'is_default' => 'boolean',
        ];
    }
}
