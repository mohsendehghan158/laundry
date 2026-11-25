<?php

declare(strict_types=1);

use App\Modules\Logistics\Support\Polygon;

it('tells points inside a zone from points outside it', function () {
    // An L shape: the notch at the top right is outside.
    $l = Polygon::from([[0, 0], [0, 4], [2, 4], [2, 2], [4, 2], [4, 0]]);

    expect($l->contains(1, 1))->toBeTrue()
        ->and($l->contains(3, 1))->toBeTrue()
        ->and($l->contains(1, 3))->toBeTrue()
        ->and($l->contains(3, 3))->toBeFalse()
        ->and($l->contains(5, 1))->toBeFalse()
        ->and($l->contains(-1, 1))->toBeFalse();
});

it('accepts the corners in either direction and drops a closing corner', function () {
    $square = Polygon::from([[35.77, 51.40], [35.77, 51.42], [35.75, 51.42], [35.75, 51.40], [35.77, 51.40]]);

    expect($square->points)->toHaveCount(4)
        ->and($square->contains(35.76, 51.41))->toBeTrue()
        ->and($square->bounds())->toBe(['min_latitude' => 35.75, 'max_latitude' => 35.77, 'min_longitude' => 51.4, 'max_longitude' => 51.42]);
});

it('refuses shapes that do not enclose an area', function (array $points) {
    expect(fn () => Polygon::from($points))->toThrow(InvalidArgumentException::class);
})->with([
    'two corners' => [[[0, 0], [1, 1]]],
    'on one line' => [[[0, 0], [1, 1], [2, 2]]],
    'off the map' => [[[0, 0], [0, 1], [95, 1]]],
    'not a pair' => [[[0, 0], [0, 1], [1]]],
    'not numbers' => [[[0, 0], [0, 'x'], [1, 1]]],
]);
