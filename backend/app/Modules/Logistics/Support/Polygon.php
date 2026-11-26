<?php

declare(strict_types=1);

namespace App\Modules\Logistics\Support;

use InvalidArgumentException;

/**
 * A simple polygon on the map, corners as [latitude, longitude]. Zones
 * are a few kilometres across, so treating degrees as a flat plane is
 * accurate enough for "is this address inside".
 */
final readonly class Polygon
{
    public const int MIN_POINTS = 3;

    public const int MAX_POINTS = 200;

    /**
     * @param  non-empty-list<array{0: float, 1: float}>  $points  at least MIN_POINTS, checked in from()
     */
    private function __construct(public array $points) {}

    /**
     * @param  array<mixed>  $points  [[lat, lng], …], as submitted or stored
     */
    public static function from(array $points): self
    {
        $clean = [];
        foreach ($points as $point) {
            if (! is_array($point) || count($point) !== 2 || ! is_numeric($point[0] ?? null) || ! is_numeric($point[1] ?? null)) {
                throw new InvalidArgumentException('Every corner must be a [latitude, longitude] pair.');
            }
            $clean[] = [round((float) $point[0], 7), round((float) $point[1], 7)];
        }

        // A closing corner equal to the first adds nothing.
        if (count($clean) > 1 && $clean[0] === $clean[count($clean) - 1]) {
            array_pop($clean);
        }

        if (count($clean) < self::MIN_POINTS || count($clean) > self::MAX_POINTS) {
            throw new InvalidArgumentException('A zone needs between 3 and 200 corners.');
        }

        foreach ($clean as [$lat, $lng]) {
            if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
                throw new InvalidArgumentException('A corner lies outside the map.');
            }
        }

        if (abs(self::signedArea($clean)) < 1e-12) {
            throw new InvalidArgumentException('The corners do not enclose an area.');
        }

        return new self($clean);
    }

    /**
     * Ray casting: a point on the edge may fall either way, which does
     * not matter at street scale.
     */
    public function contains(float $latitude, float $longitude): bool
    {
        $inside = false;
        $count = count($this->points);

        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            [$latI, $lngI] = $this->points[$i];
            [$latJ, $lngJ] = $this->points[$j];

            if (($latI > $latitude) !== ($latJ > $latitude)
                && $longitude < ($lngJ - $lngI) * ($latitude - $latI) / ($latJ - $latI) + $lngI) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }

    /**
     * @return array{min_latitude: float, max_latitude: float, min_longitude: float, max_longitude: float}
     */
    public function bounds(): array
    {
        $lats = array_column($this->points, 0);
        $lngs = array_column($this->points, 1);

        return [
            'min_latitude' => min($lats),
            'max_latitude' => max($lats),
            'min_longitude' => min($lngs),
            'max_longitude' => max($lngs),
        ];
    }

    /**
     * @param  list<array{0: float, 1: float}>  $points
     */
    private static function signedArea(array $points): float
    {
        $sum = 0.0;
        $count = count($points);
        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            $sum += ($points[$j][1] * $points[$i][0]) - ($points[$i][1] * $points[$j][0]);
        }

        return $sum / 2;
    }
}
