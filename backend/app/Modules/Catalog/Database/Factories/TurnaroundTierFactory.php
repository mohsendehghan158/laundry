<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Database\Factories;

use App\Modules\Catalog\Models\TurnaroundTier;
use App\Modules\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TurnaroundTier>
 */
final class TurnaroundTierFactory extends Factory
{
    protected $model = TurnaroundTier::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => 'عادی',
            'duration_hours' => 72,
            'surcharge_percent' => 0,
            'is_default' => false,
        ];
    }
}
