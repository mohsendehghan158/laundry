<?php

declare(strict_types=1);

use App\Modules\Billing\Support\Allocator;
use App\Support\Text\NationalId;

it('splits an amount over weights so the parts add up', function (int $amount, array $weights, array $parts) {
    expect(Allocator::split($amount, $weights))->toBe($parts);
})->with([
    'even' => [100, [1, 1], [50, 50]],
    'leftover to the largest remainder' => [710_000, [3_510_000, 3_200_000, 2_000_000], [286_119, 260_850, 163_031]],
    'ties go to the earlier part' => [10, [1, 1, 1], [4, 3, 3]],
    'zero weights split evenly' => [5, [0, 0], [3, 2]],
    'nothing' => [0, [5, 7], [0, 0]],
    'no parts' => [10, [], []],
]);

it('checks Iranian national codes and ids', function () {
    expect(NationalId::isValidPersonCode('0013542419'))->toBeTrue()
        ->and(NationalId::isValidPersonCode('1234567891'))->toBeTrue()
        ->and(NationalId::isValidPersonCode('0013542418'))->toBeFalse()
        ->and(NationalId::isValidPersonCode('1111111111'))->toBeFalse()
        ->and(NationalId::isValidPersonCode('123'))->toBeFalse()
        ->and(NationalId::isValidLegalId('10101234565'))->toBeTrue()
        ->and(NationalId::isValidLegalId('10862012343'))->toBeTrue()
        ->and(NationalId::isValidLegalId('10101234566'))->toBeFalse()
        ->and(NationalId::isValidLegalId('1010123456'))->toBeFalse();
});
