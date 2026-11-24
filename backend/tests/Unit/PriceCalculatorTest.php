<?php

declare(strict_types=1);

use App\Modules\Catalog\Enums\UpchargeType;
use App\Modules\Orders\Enums\DiscountType;
use App\Modules\Orders\Services\PriceCalculator;

function line(int $price, float $quantity = 1, array $upcharges = []): array
{
    return [
        'unit_price' => $price,
        'quantity_milli' => PriceCalculator::milli($quantity),
        'upcharges' => array_map(fn (array $u): array => ['type' => $u[0], 'value' => $u[1]], $upcharges),
    ];
}

it('multiplies price by quantity, including fractional kilograms', function () {
    $quote = (new PriceCalculator)->calculate([line(900_000, 3), line(800_000, 2.5)], 0, null, 0, false);

    expect($quote['lines'][0]['total'])->toBe(2_700_000)
        ->and($quote['lines'][1]['total'])->toBe(2_000_000)
        ->and($quote['subtotal'])->toBe(4_700_000)
        ->and($quote['total'])->toBe(4_700_000);
});

it('applies fixed upcharges per unit and percent upcharges on the base', function () {
    $quote = (new PriceCalculator)->calculate(
        [line(1_000_000, 2, [[UpchargeType::Fixed, 300_000], [UpchargeType::Percent, 30]])],
        0, null, 0, false,
    );

    // base 2,000,000; fixed 2 × 300,000; 30% of base 600,000
    expect($quote['lines'][0])->toBe(['base' => 2_000_000, 'upcharge' => 1_200_000, 'upcharges' => [600_000, 600_000], 'surcharge' => 0, 'total' => 3_200_000]);
});

it('adds the turnaround surcharge on the line including upcharges', function () {
    $quote = (new PriceCalculator)->calculate([line(1_000_000, 1, [[UpchargeType::Fixed, 200_000]])], 50, null, 0, false);

    expect($quote['lines'][0]['surcharge'])->toBe(600_000)
        ->and($quote['total'])->toBe(1_800_000);
});

it('takes a discount before VAT and never below zero', function () {
    $calculator = new PriceCalculator;

    $percent = $calculator->calculate([line(1_000_000)], 0, ['type' => DiscountType::Percent, 'value_bp' => 1000], 1000, false);
    expect($percent)->toMatchArray(['subtotal' => 1_000_000, 'discount' => 100_000, 'tax' => 90_000, 'total' => 990_000]);

    $amount = $calculator->calculate([line(1_000_000)], 0, ['type' => DiscountType::Amount, 'value_bp' => 5_000_000], 1000, false);
    expect($amount)->toMatchArray(['discount' => 1_000_000, 'tax' => 0, 'total' => 0]);
});

it('extracts VAT from prices that already include it', function () {
    $quote = (new PriceCalculator)->calculate([line(1_090_000)], 0, null, 900, true);

    expect($quote)->toMatchArray(['subtotal' => 1_090_000, 'tax' => 90_000, 'total' => 1_090_000]);
});

it('handles fractional VAT rates and rounds half up to whole rials', function () {
    $quote = (new PriceCalculator)->calculate([line(333_333)], 0, null, 950, false);

    // 333,333 × 9.5% = 31,666.635 → 31,667
    expect($quote['tax'])->toBe(31_667)->and($quote['total'])->toBe(365_000);
});

it('gives an empty order a zero total', function () {
    expect((new PriceCalculator)->calculate([], 50, null, 1000, false))->toMatchArray(['subtotal' => 0, 'total' => 0]);
});

it('refuses non-positive quantities', function () {
    (new PriceCalculator)->calculate([line(1000, 0)], 0, null, 0, false);
})->throws(InvalidArgumentException::class);
