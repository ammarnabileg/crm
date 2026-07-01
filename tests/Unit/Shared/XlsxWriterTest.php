<?php

declare(strict_types=1);

namespace HaHireAI\Tests\Unit\Shared;

use HaHireAI\Shared\XlsxWriter;
use PHPUnit\Framework\TestCase;
use ZipArchive;

/** Native .xlsx writer produces a valid OOXML workbook (no external libraries). */
final class XlsxWriterTest extends TestCase
{
    public function test_produces_a_valid_xlsx_package(): void
    {
        $bytes = XlsxWriter::fromRows([
            ['Candidate', 'Score', 'ULID'],
            ['Sara', 87, '01KW7NMAZJV7G0736WFC23TG7V'],
            ['Omar', 72, '007'],
            ['A & B <ok>', null, ''],
        ], 'AI Interviews');

        $this->assertNotSame('', $bytes);

        $tmp = (string) tempnam(sys_get_temp_dir(), 'xlsxtest');
        file_put_contents($tmp, $bytes);

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($tmp) === true);
        foreach ([
            '[Content_Types].xml',
            '_rels/.rels',
            'xl/workbook.xml',
            'xl/_rels/workbook.xml.rels',
            'xl/worksheets/sheet1.xml',
        ] as $part) {
            $this->assertNotFalse($zip->locateName($part), "missing part {$part}");
        }

        $sheet = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
        $workbook = (string) $zip->getFromName('xl/workbook.xml');
        $zip->close();
        @unlink($tmp);

        // Well-formed XML.
        $this->assertNotFalse(simplexml_load_string($sheet));

        // Numbers are numeric cells; identifiers stay strings.
        $this->assertStringContainsString('<v>87</v>', $sheet);
        $this->assertStringContainsString('01KW7NMAZJV7G0736WFC23TG7V', $sheet);
        $this->assertStringNotContainsString('<v>01KW', $sheet);
        $this->assertStringNotContainsString('<v>007</v>', $sheet); // leading zero ⇒ string
        $this->assertStringContainsString('>007<', $sheet);

        // XML special chars are escaped.
        $this->assertStringContainsString('A &amp; B &lt;ok&gt;', $sheet);

        // Sheet name is carried (and sanitised to ≤31 chars).
        $this->assertStringContainsString('name="AI Interviews"', $workbook);
    }

    public function test_sheet_name_is_sanitised(): void
    {
        $bytes = XlsxWriter::fromRows([['x']], 'Bad/Name:With*Chars[here]and-way-too-long-to-fit-31');
        $tmp = (string) tempnam(sys_get_temp_dir(), 'xlsxtest');
        file_put_contents($tmp, $bytes);
        $zip = new ZipArchive();
        $zip->open($tmp);
        $workbook = (string) $zip->getFromName('xl/workbook.xml');
        $zip->close();
        @unlink($tmp);

        $this->assertMatchesRegularExpression('/name="[^"]{1,31}"/', $workbook);
        $this->assertStringNotContainsString('/', $workbook === '' ? '/' : (string) preg_replace('/.*name="([^"]*)".*/s', '$1', $workbook));
    }
}
