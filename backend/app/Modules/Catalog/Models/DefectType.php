<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Concerns\CatalogEntry;
use App\Modules\Catalog\Database\Factories\DefectTypeFactory;
use App\Modules\Catalog\Enums\DefectKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property DefectKind $kind
 * @property int $sort_order
 * @property bool $is_active
 */
#[Fillable(['name', 'kind', 'sort_order', 'is_active'])]
#[UseFactory(DefectTypeFactory::class)]
class DefectType extends Model
{
    use CatalogEntry;

    /** @use HasFactory<DefectTypeFactory> */
    use HasFactory;

    use SoftDeletes;

    /** @var array<string, mixed> */
    protected $attributes = [
        'sort_order' => 0,
        'is_active' => true,
        'kind' => 'defect',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'kind' => DefectKind::class,
        ];
    }
}
