<?php

declare(strict_types=1);

use App\Support\Export\XlsxWriter;

it('writes a workbook with a bold header, text, numbers and a right-to-left sheet', function () {
    $bytes = XlsxWriter::build('فروش روزانه', ['تاریخ', 'مبلغ'], [['۱۴۰۵/۰۹/۰۳', 990000], ['<جمع & کل>', 1.5], [null, 3]]);

    $path = tempnam(sys_get_temp_dir(), 'test');
    file_put_contents($path, $bytes);
    $zip = new ZipArchive;
    expect($zip->open($path))->toBeTrue();

    $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
    $workbook = $zip->getFromName('xl/workbook.xml');
    $zip->close();
    unlink($path);

    expect($workbook)->toContain('name="فروش روزانه"')
        ->and($sheet)->toContain('rightToLeft="1"')
        ->and($sheet)->toContain('<c r="A1" t="inlineStr" s="1"><is><t xml:space="preserve">تاریخ</t></is></c>')
        ->and($sheet)->toContain('<c r="B2" s="2"><v>990000</v></c>')
        ->and($sheet)->toContain('&lt;جمع &amp; کل&gt;')
        ->and($sheet)->toContain('<c r="B4" s="2"><v>3</v></c>')
        ->and($sheet)->not->toContain('r="A4"');

    // Well-formed XML throughout.
    expect(simplexml_load_string($sheet))->not->toBeFalse();
});
