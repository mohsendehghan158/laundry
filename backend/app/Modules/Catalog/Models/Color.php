<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Concerns\CatalogEntry;
use App\Modules\Catalog\Database\Factories\ColorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property string|null $hex
 * @property int $sort_order
 * @property bool $is_active
 */
#[Fillable(['name', 'hex', 'sort_order', 'is_active'])]
#[UseFactory(ColorFactory::class)]
class Color extends Model
{
    use CatalogEntry;

    /** @use HasFactory<ColorFactory> */
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
