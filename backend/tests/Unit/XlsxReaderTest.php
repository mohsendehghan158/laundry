<?php

declare(strict_types=1);

use App\Support\Export\XlsxReader;
use App\Support\Export\XlsxWriter;

/**
 * @param  array<string, string>  $parts
 */
function workbookFile(array $parts): string
{
    $path = (string) tempnam(sys_get_temp_dir(), 'xlsx');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::OVERWRITE);
    foreach ($parts as $name => $content) {
        $zip->addFromString($name, $content);
    }
    $zip->close();

    return $path;
}

it('reads back what XlsxWriter writes', function () {
    $path = (string) tempnam(sys_get_temp_dir(), 'xlsx');
    file_put_contents($path, XlsxWriter::build('قیمت', ['کالا', 'خشکشویی'], [['پیراهن', 90000], ['کت', null], ['شال', 12.5]]));

    expect(XlsxReader::firstSheet($path))->toBe([
        ['کالا', 'خشکشویی'],
        ['پیراهن', 90000],
        ['کت', null],
        ['شال', 12.5],
    ]);
    unlink($path);
});

it('reads shared strings, rich text, sparse cells and gaps the way Excel saves them', function () {
    $main = 'xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"';
    $path = workbookFile([
        'xl/workbook.xml' => '<?xml version="1.0"?><workbook '.$main.' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Prices" sheetId="1" r:id="rId3"/></sheets></workbook>',
        'xl/_rels/workbook.xml.rels' => '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId3" Type="worksheet" Target="worksheets/data.xml"/></Relationships>',
        'xl/sharedStrings.xml' => '<?xml version="1.0"?><sst '.$main.'><si><t>شناسه</t></si><si><r><t>خشک</t></r><r><t>شویی</t></r><rPh><t>x</t></rPh></si></sst>',
        'xl/worksheets/data.xml' => '<?xml version="1.0"?><worksheet '.$main.'><sheetData>'
            .'<row r="1"><c r="A1" t="s"><v>0</v></c><c r="C1" t="s"><v>1</v></c></row>'
            .'<row r="3"><c r="A3"><v>7</v></c><c r="C3" t="str"><v>۱۲۰٬۰۰۰</v></c><c r="D3" t="e"><v>#REF!</v></c></row>'
            .'<row r="4"><c r="B4" t="inlineStr"><is><t>متن</t></is></c><c r="C4"><f>1+1</f><v>2.75</v></c></row>'
            .'</sheetData></worksheet>',
    ]);

    expect(XlsxReader::firstSheet($path))->toBe([
        ['شناسه', null, 'خشکشویی'],
        [null, null, null],
        [7, null, '۱۲۰٬۰۰۰'],
        [null, 'متن', 2.75],
    ]);
    unlink($path);
});

it('refuses files that are not workbooks, have document types or too many rows', function () {
    $text = (string) tempnam(sys_get_temp_dir(), 'txt');
    file_put_contents($text, 'hello');
    expect(fn () => XlsxReader::firstSheet($text))->toThrow(InvalidArgumentException::class, 'not_a_workbook');

    $doctype = workbookFile(['xl/worksheets/sheet1.xml' => '<?xml version="1.0"?><!DOCTYPE x [<!ENTITY a "aaaa">]><worksheet><sheetData/></worksheet>']);
    expect(fn () => XlsxReader::firstSheet($doctype))->toThrow(InvalidArgumentException::class, 'not_a_workbook');

    $long = workbookFile(['xl/worksheets/sheet1.xml' => '<?xml version="1.0"?><worksheet><sheetData><row r="12"><c r="A12"><v>1</v></c></row></sheetData></worksheet>']);
    expect(fn () => XlsxReader::firstSheet($long, 10))->toThrow(InvalidArgumentException::class, 'too_many_rows');

    array_map(unlink(...), [$text, $doctype, $long]);
});
