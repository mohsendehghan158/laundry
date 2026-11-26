<?php

declare(strict_types=1);

namespace App\Modules\Logistics\Database\Factories;

use App\Modules\Logistics\Models\Zone;
use App\Modules\Logistics\Support\Polygon;
use App\Modules\Organization\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Zone>
 */
final class ZoneFactory extends Factory
{
    protected $model = Zone::class;

    /** A square around Vanak, Tehran (35.75–35.77, 51.40–51.42). */
    public const array VANAK = [[35.75, 51.40], [35.75, 51.42], [35.77, 51.42], [35.77, 51.40]];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $polygon = Polygon::from(self::VANAK);

        return [
            'organization_id' => fn (array $attributes) => Branch::query()->withoutGlobalScopes()->findOrFail((int) $attributes['branch_id'])->organization_id,
            'name' => 'محدوده '.fake()->unique()->numberBetween(1, 99999),
            'polygon' => $polygon->points,
            ...$polygon->bounds(),
            'delivery_fee' => 300_000,
        ];
    }

    /**
     * @param  list<array{0: float, 1: float}>  $points
     */
    public function shaped(array $points): self
    {
        $polygon = Polygon::from($points);

        return $this->state(['polygon' => $polygon->points, ...$polygon->bounds()]);
    }
}
