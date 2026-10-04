<?php

declare(strict_types=1);

namespace HaHireAI\Shared;

use ZipArchive;

/**
 * Minimal, dependency-free writer for native .xlsx (OOXML SpreadsheetML).
 * An .xlsx file is a ZIP of XML parts; we emit the smallest valid set using
 * inline strings (no shared-strings table) so any single worksheet of tabular
 * data round-trips cleanly into Excel/Sheets/LibreOffice.
 */
final class XlsxWriter
{
    /**
     * Build a one-sheet workbook and return the raw .xlsx bytes.
     *
     * @param  list<list<string|int|float|null>>  $rows  the first row is treated as data too; pass a header row first if wanted
     */
    public static function fromRows(array $rows, string $sheetName = 'Sheet1'): string
    {
        $sheet = self::sheetXml($rows);
        $name = self::safeSheetName($sheetName);

        $parts = [
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
                . '<Default Extension="xml" ContentType="application/xml"/>'
                . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
                . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
                . '</Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
                . '</Relationships>',
            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                . '<sheets><sheet name="' . self::xml($name) . '" sheetId="1" r:id="rId1"/></sheets>'
                . '</workbook>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
                . '</Relationships>',
            'xl/worksheets/sheet1.xml' => $sheet,
        ];

        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        if ($tmp === false) {
            throw new \RuntimeException('Could not allocate a temp file for the workbook.');
        }

        $zip = new ZipArchive();
        if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
            @unlink($tmp);
            throw new \RuntimeException('Could not open the workbook archive.');
        }
        foreach ($parts as $path => $contents) {
            $zip->addFromString($path, $contents);
        }
        $zip->close();

        $bytes = (string) file_get_contents($tmp);
        @unlink($tmp);

        return $bytes;
    }

    /** @param list<list<string|int|float|null>> $rows */
    private static function sheetXml(array $rows): string
    {
        $body = '';
        foreach ($rows as $r => $row) {
            $rowNum = $r + 1;
            $cells = '';
            $col = 0;
            foreach ($row as $value) {
                $ref = self::columnLetter($col) . $rowNum;
                $cells .= self::cell($ref, $value);
                $col++;
            }
            $body .= '<row r="' . $rowNum . '">' . $cells . '</row>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetData>' . $body . '</sheetData></worksheet>';
    }

    private static function cell(string $ref, string|int|float|null $value): string
    {
        if ($value === null || $value === '') {
            return '<c r="' . $ref . '"/>';
        }

        // Emit clean numeric cells for safe numbers; everything else is an inline
        // string (so ULIDs, leading zeros and long digit runs are never mangled).
        if (is_int($value) || is_float($value) || self::isSafeNumber((string) $value)) {
            return '<c r="' . $ref . '"><v>' . self::xml((string) $value) . '</v></c>';
        }

        return '<c r="' . $ref . '" t="inlineStr"><is><t xml:space="preserve">' . self::xml((string) $value) . '</t></is></c>';
    }

    private static function isSafeNumber(string $v): bool
    {
        if (! preg_match('/^-?(0|[1-9]\d{0,13})(\.\d+)?$/', $v)) {
            return false;
        }

        return true;
    }

    private static function columnLetter(int $index): string
    {
        $letter = '';
        $index++;
        while ($index > 0) {
            $rem = ($index - 1) % 26;
            $letter = chr(65 + $rem) . $letter;
            $index = intdiv($index - 1, 26);
        }

        return $letter;
    }

    private static function safeSheetName(string $name): string
    {
        $clean = preg_replace('/[\\\\\\/\\?\\*\\[\\]:]/', ' ', $name) ?? 'Sheet1';
        $clean = trim($clean);

        return $clean === '' ? 'Sheet1' : mb_substr($clean, 0, 31);
    }

    private static function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
