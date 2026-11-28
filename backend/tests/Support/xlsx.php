<?php

declare(strict_types=1);

/**
 * The first worksheet's XML of an .xlsx file.
 */
function sheetXml(string|false $bytes): string
{
    $path = (string) tempnam(sys_get_temp_dir(), 'xlsx');
    file_put_contents($path, (string) $bytes);
    $zip = new ZipArchive;
    $zip->open($path);
    $sheet = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    unlink($path);

    return $sheet;
}
