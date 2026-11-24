<?php

declare(strict_types=1);

use App\Support\Text\Mobile;

it('normalizes the ways people type an Iranian mobile number', function (string $input) {
    expect(Mobile::normalize($input))->toBe('09121234567');
})->with([
    '09121234567',
    '۰۹۱۲۱۲۳۴۵۶۷',
    '0912 123 4567',
    '0912-123-4567',
    '9121234567',
    '+989121234567',
    '00989121234567',
    '989121234567',
    ' ۰۹۱۲-۱۲۳-۴۵۶۷ ',
]);

it('leaves things that are not mobile numbers for validation to reject', function () {
    expect(Mobile::normalize('021-88001122'))->toBe('02188001122')
        ->and(Mobile::normalize('abc'))->toBe('abc');
});

it('extracts searchable digits from a partial number', function () {
    expect(Mobile::searchable('۰۹۱۲ ۱۲'))->toBe('091212')
        ->and(Mobile::searchable('+98912'))->toBe('0912')
        ->and(Mobile::searchable('علی'))->toBe('');
});
