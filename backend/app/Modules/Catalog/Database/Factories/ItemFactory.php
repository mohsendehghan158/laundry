<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Database\Factories;

use App\Modules\Catalog\Enums\PriceUnit;
use App\Modules\Catalog\Models\Item;
use App\Modules\Catalog\Models\ItemCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Item>
 */
final class ItemFactory extends Factory
{
    protected $model = Item::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'item_category_id' => ItemCategory::factory(),
            'organization_id' => fn (array $attributes): int => ItemCategory::query()
                ->withoutGlobalScopes()
                ->findOrFail($attributes['item_category_id'])
                ->organization_id,
            'name' => 'قلم '.fake()->unique()->numberBetween(1, 99999),
            'unit' => PriceUnit::Piece,
            'pieces_per_unit' => 1,
            'icon' => null,
        ];
    }
}
