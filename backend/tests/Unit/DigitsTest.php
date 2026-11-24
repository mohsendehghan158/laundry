<?php

declare(strict_types=1);

use App\Support\Text\Digits;

it('converts Persian and Arabic digits to Latin', function (string $input, string $expected) {
    expect(Digits::toLatin($input))->toBe($expected);
})->with([
    'persian' => ['۰۹۱۲۳۴۵۶۷۸۹', '09123456789'],
    'arabic' => ['٠١٢٣٤٥٦٧٨٩', '0123456789'],
    'mixed with text' => ['پلاک ۱۲، واحد ٣', 'پلاک 12، واحد 3'],
    'already latin' => ['T1-04217', 'T1-04217'],
    'empty' => ['', ''],
]);
