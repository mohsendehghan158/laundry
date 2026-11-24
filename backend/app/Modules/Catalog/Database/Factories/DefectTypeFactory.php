<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Database\Factories;

use App\Modules\Catalog\Enums\DefectKind;
use App\Modules\Catalog\Models\DefectType;
use App\Modules\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DefectType>
 */
final class DefectTypeFactory extends Factory
{
    protected $model = DefectType::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => 'عیب '.fake()->unique()->numberBetween(1, 99999),
            'kind' => DefectKind::Defect,
        ];
    }
}
