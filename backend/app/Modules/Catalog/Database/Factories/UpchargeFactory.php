<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Database\Factories;

use App\Modules\Catalog\Enums\UpchargeType;
use App\Modules\Catalog\Models\Upcharge;
use App\Modules\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Upcharge>
 */
final class UpchargeFactory extends Factory
{
    protected $model = Upcharge::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => 'اضافه‌بها '.fake()->unique()->numberBetween(1, 99999),
            'type' => UpchargeType::Fixed,
            'value' => 50_000,
        ];
    }
}
