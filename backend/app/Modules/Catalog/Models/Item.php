<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Concerns\CatalogEntry;
use App\Modules\Catalog\Database\Factories\ItemFactory;
use App\Modules\Catalog\Enums\PriceUnit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Something a customer brings in: shirt, suit, blanket, carpet …
 *
 * @property int $id
 * @property int $organization_id
 * @property int $item_category_id
 * @property string $name
 * @property PriceUnit $unit
 * @property int $pieces_per_unit physical pieces (and tags) one unit makes
 * @property string|null $icon
 * @property int $sort_order
 * @property bool $is_active
 * @property-read ItemCategory|null $category archived categories are not loaded
 */
#[Fillable(['item_category_id', 'name', 'unit', 'pieces_per_unit', 'icon', 'sort_order', 'is_active'])]
#[UseFactory(ItemFactory::class)]
class Item extends Model
{
    use CatalogEntry;

    /** @use HasFactory<ItemFactory> */
    use HasFactory;

    use SoftDeletes;

    /** @var array<string, mixed> */
    protected $attributes = [
        'unit' => 'piece',
        'pieces_per_unit' => 1,
        'sort_order' => 0,
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit' => PriceUnit::class,
            'pieces_per_unit' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<ItemCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ItemCategory::class, 'item_category_id');
    }
}
