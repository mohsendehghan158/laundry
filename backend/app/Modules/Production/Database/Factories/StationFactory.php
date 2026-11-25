<?php

declare(strict_types=1);

namespace App\Modules\Production\Database\Factories;

use App\Modules\Organization\Models\Branch;
use App\Modules\Production\Models\Station;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Station>
 */
final class StationFactory extends Factory
{
    protected $model = Station::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => fn (array $attributes) => Branch::query()->withoutGlobalScopes()->findOrFail($attributes['branch_id'])->organization_id,
            'name' => 'ایستگاه '.fake()->unique()->numberBetween(1, 99999),
            'code' => strtoupper(fake()->unique()->bothify('??#')),
            'kind' => 'process',
        ];
    }
}
