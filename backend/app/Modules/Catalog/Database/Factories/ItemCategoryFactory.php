<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Database\Factories;

use App\Modules\Catalog\Models\ItemCategory;
use App\Modules\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ItemCategory>
 */
final class ItemCategoryFactory extends Factory
{
    protected $model = ItemCategory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => 'دسته '.fake()->unique()->numberBetween(1, 99999),
            'icon' => null,
            'color' => null,
        ];
    }
}
