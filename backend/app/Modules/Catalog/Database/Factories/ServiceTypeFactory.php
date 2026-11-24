<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Database\Factories;

use App\Modules\Catalog\Models\ServiceType;
use App\Modules\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceType>
 */
final class ServiceTypeFactory extends Factory
{
    protected $model = ServiceType::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => 'خدمت '.fake()->unique()->numberBetween(1, 99999),
            'code' => strtoupper(fake()->unique()->bothify('??#')),
            'color' => null,
        ];
    }
}
