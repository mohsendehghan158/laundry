<?php

declare(strict_types=1);

namespace App\Support\Export;

use InvalidArgumentException;
use SimpleXMLElement;
use ZipArchive;

/**
 * Reads the first worksheet of an .xlsx workbook into rows of values:
 * strings for text, int or float for numbers, null for empty cells.
 * Enough for importing simple sheets written by Excel, LibreOffice,
 * Google Sheets or XlsxWriter; formulas are read as their cached result.
 */
final class XlsxReader
{
    /** Uncompressed parts above this are refused, against zip bombs. */
    private const int MAX_PART_BYTES = 20 * 1024 * 1024;

    private const int MAX_COLUMNS = 200;

    /**
     * @return list<list<string|int|float|null>> rows of the first sheet, padded to the widest row; trailing empty rows dropped
     *
     * @throws InvalidArgumentException when the file is not a readable workbook or has more than `$maxRows` rows
     */
    public static function firstSheet(string $path, int $maxRows = 5000): array
    {
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new InvalidArgumentException('not_a_workbook');
        }

        try {
            $strings = self::sharedStrings($zip);
            $sheet = self::xml($zip, self::firstSheetPath($zip));

            if ($sheet === null) {
                throw new InvalidArgumentException('not_a_workbook');
            }

            return self::rows($sheet, $strings, $maxRows);
        } finally {
            $zip->close();
        }
    }

    private static function firstSheetPath(ZipArchive $zip): string
    {
        $fallback = 'xl/worksheets/sheet1.xml';
        $workbook = self::xml($zip, 'xl/workbook.xml');
        $relations = self::xml($zip, 'xl/_rels/workbook.xml.rels');

        if ($workbook === null || $relations === null) {
            return $fallback;
        }

        $sheets = $workbook->xpath('//*[local-name()="sheets"]/*[local-name()="sheet"]') ?: [];
        $first = $sheets[0] ?? null;
        $id = $first === null ? null : (string) $first->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];

        foreach ($relations->xpath('//*[local-name()="Relationship"]') ?: [] as $relation) {
            if ((string) $relation['Id'] === $id) {
                $target = (string) $relation['Target'];

                return str_starts_with($target, '/') ? ltrim($target, '/') : 'xl/'.$target;
            }
        }

        return $fallback;
    }

    /**
     * @return list<string>
     */
    private static function sharedStrings(ZipArchive $zip): array
    {
        $xml = self::xml($zip, 'xl/sharedStrings.xml');

        if ($xml === null) {
            return [];
        }

        return array_values(array_map(self::text(...), $xml->xpath('/*/*[local-name()="si"]') ?: []));
    }

    /**
     * The text of a string item: plain or rich text runs, without the
     * phonetic hints Excel adds for East Asian text.
     */
    private static function text(SimpleXMLElement $node): string
    {
        $parts = $node->xpath('.//*[local-name()="t"][not(ancestor::*[local-name()="rPh"])]') ?: [];

        return implode('', array_map(fn (SimpleXMLElement $t): string => (string) $t, $parts));
    }

    /**
     * @param  list<string>  $strings
     * @return list<list<string|int|float|null>>
     */
    private static function rows(SimpleXMLElement $sheet, array $strings, int $maxRows): array
    {
        /** @var array<int, array<int, string|int|float|null>> $cells */
        $cells = [];
        $width = 0;
        $next = 0;

        foreach ($sheet->xpath('//*[local-name()="sheetData"]/*[local-name()="row"]') ?: [] as $row) {
            $index = isset($row['r']) ? (int) $row['r'] - 1 : $next;
            $next = $index + 1;

            if ($index >= $maxRows) {
                throw new InvalidArgumentException('too_many_rows');
            }

            $column = 0;
            foreach ($row->xpath('./*[local-name()="c"]') ?: [] as $cell) {
                $column = isset($cell['r']) ? self::columnIndex((string) $cell['r']) : $column;

                if ($column >= self::MAX_COLUMNS) {
                    continue;
                }

                $value = self::value($cell, $strings);
                if ($value !== null) {
                    $cells[$index][$column] = $value;
                    $width = max($width, $column + 1);
                }
                $column++;
            }
        }

        if ($cells === []) {
            return [];
        }

        $rows = [];
        for ($i = 0, $last = max(array_keys($cells)); $i <= $last; $i++) {
            $line = array_fill(0, $width, null);
            foreach ($cells[$i] ?? [] as $column => $value) {
                $line[$column] = $value;
            }
            $rows[] = array_values($line);
        }

        return $rows;
    }

    /**
     * @param  list<string>  $strings
     */
    private static function value(SimpleXMLElement $cell, array $strings): string|int|float|null
    {
        $type = (string) ($cell['t'] ?? 'n');
        $raw = $cell->xpath('./*[local-name()="v"]')[0] ?? null;
        $raw = $raw === null ? null : (string) $raw;

        $value = match ($type) {
            's' => $raw === null ? null : ($strings[(int) $raw] ?? null),
            'inlineStr' => self::text(($cell->xpath('./*[local-name()="is"]') ?: [$cell])[0]),
            'str' => $raw,
            'e' => null,
            default => $raw === null || ! is_numeric($raw) ? null : self::number($raw),
        };

        return $value === '' ? null : $value;
    }

    private static function number(string $raw): int|float
    {
        $number = (float) $raw;

        return floor($number) === $number && abs($number) < PHP_INT_MAX ? (int) $number : $number;
    }

    /** "B7" → 1 */
    private static function columnIndex(string $reference): int
    {
        $index = 0;
        foreach (str_split(strtoupper((string) preg_replace('/\d+/', '', $reference))) as $letter) {
            $index = $index * 26 + (ord($letter) - 64);
        }

        return max(0, $index - 1);
    }

    private static function xml(ZipArchive $zip, string $name): ?SimpleXMLElement
    {
        $stat = $zip->statName($name);

        if ($stat === false) {
            return null;
        }
        if ($stat['size'] > self::MAX_PART_BYTES) {
            throw new InvalidArgumentException('too_large');
        }

        $content = $zip->getFromName($name);

        // No document types: they are how entity-expansion attacks start.
        if ($content === false || stripos($content, '<!DOCTYPE') !== false) {
            throw new InvalidArgumentException('not_a_workbook');
        }

        $previous = libxml_use_internal_errors(true);
        try {
            $xml = simplexml_load_string($content, SimpleXMLElement::class, LIBXML_NONET | LIBXML_COMPACT);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if ($xml === false) {
            throw new InvalidArgumentException('not_a_workbook');
        }

        return $xml;
    }
}
