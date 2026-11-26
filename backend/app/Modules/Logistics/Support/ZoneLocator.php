<?php

declare(strict_types=1);

namespace App\Modules\Logistics\Support;

use App\Modules\Logistics\Models\Zone;

/**
 * Finds the active zone a point lies in. Where zones overlap the one
 * created first wins, so drawing a new zone never takes addresses away
 * from an existing one.
 */
final class ZoneLocator
{
    public function find(float|string|null $latitude, float|string|null $longitude): ?Zone
    {
        if (! is_numeric($latitude) || ! is_numeric($longitude)) {
            return null;
        }
        $lat = (float) $latitude;
        $lng = (float) $longitude;

        $candidates = Zone::query()
            ->with('branch')
            ->where('is_active', true)
            ->where('min_latitude', '<=', $lat)
            ->where('max_latitude', '>=', $lat)
            ->where('min_longitude', '<=', $lng)
            ->where('max_longitude', '>=', $lng)
            ->orderBy('id')
            ->get();

        foreach ($candidates as $zone) {
            // A zone whose branch was closed or deleted (loaded with trashed) serves nobody.
            if ($zone->branch->trashed() || ! $zone->branch->is_active) {
                continue;
            }
            if ($zone->shape()->contains($lat, $lng)) {
                return $zone;
            }
        }

        return null;
    }
}
