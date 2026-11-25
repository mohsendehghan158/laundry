<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Logistics\Models\SlotTemplate;
use App\Modules\Logistics\Models\Zone;
use App\Modules\Logistics\Support\Polygon;
use App\Modules\Organization\Models\Branch;

/** Demo service zones in Tehran and weekly pickup windows for two branches. */
final class DemoLogisticsSeeder
{
    public function run(int $organizationId, Branch $central, Branch $west): void
    {
        // Vanak, Mirdamad, Jordan: served by the central branch.
        $this->zone($organizationId, $central, 'شمال و مرکز', [[35.74, 51.38], [35.74, 51.46], [35.80, 51.46], [35.80, 51.38]], 300_000, 5_000_000, 1_000_000);
        // Punak, Saadat Abad, Ferdows: served by the west branch.
        $this->zone($organizationId, $west, 'غرب', [[35.71, 51.30], [35.71, 51.38], [35.80, 51.38], [35.80, 51.30]], 400_000, 6_000_000, 1_000_000);

        // Every day but Friday (5): mornings and evenings.
        foreach ([0, 1, 2, 3, 4, 6] as $weekday) {
            $this->window($organizationId, $central, $weekday, '09:00', '12:00', 6);
            $this->window($organizationId, $central, $weekday, '16:00', '20:00', 8);
        }
        // Saturday to Wednesday.
        foreach ([6, 0, 1, 2, 3] as $weekday) {
            $this->window($organizationId, $west, $weekday, '10:00', '13:00', 4);
            $this->window($organizationId, $west, $weekday, '17:00', '20:00', 4);
        }
    }

    /**
     * @param  list<array{0: float, 1: float}>  $points
     */
    private function zone(int $organizationId, Branch $branch, string $name, array $points, int $fee, int $freeOver, int $minOrder): void
    {
        $polygon = Polygon::from($points);
        Zone::query()->forceCreate([
            'organization_id' => $organizationId,
            'branch_id' => $branch->id,
            'name' => $name,
            'polygon' => $polygon->points,
            ...$polygon->bounds(),
            'delivery_fee' => $fee,
            'free_delivery_over' => $freeOver,
            'min_order' => $minOrder,
        ]);
    }

    private function window(int $organizationId, Branch $branch, int $weekday, string $from, string $to, int $capacity): void
    {
        SlotTemplate::query()->forceCreate([
            'organization_id' => $organizationId,
            'branch_id' => $branch->id,
            'weekday' => $weekday,
            'starts_at' => $from,
            'ends_at' => $to,
            'capacity' => $capacity,
        ]);
    }
}
