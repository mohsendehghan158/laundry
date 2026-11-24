<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Concerns\CatalogEntry;
use App\Modules\Catalog\Database\Factories\ServiceTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property string $code
 * @property string|null $color
 * @property int $sort_order
 * @property bool $is_active
 */
#[Fillable(['name', 'code', 'color', 'sort_order', 'is_active'])]
#[UseFactory(ServiceTypeFactory::class)]
class ServiceType extends Model
{
    use CatalogEntry;

    /** @use HasFactory<ServiceTypeFactory> */
    use HasFactory;

    use SoftDeletes;

    /** @var array<string, mixed> */
    protected $attributes = [
        'sort_order' => 0,
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
