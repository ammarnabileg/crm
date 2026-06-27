<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Cv\CvExtractor;
use Tests\TestCase;

/**
 * CvExtractor — pure-PHP text extraction from the formats a CV arrives in. Builds a
 * real DOCX (a zip of XML), an RTF and a plain-text file at runtime and asserts the
 * visible text is recovered, plus that obvious binary noise is reported low-confidence.
 */
return new class extends TestCase {
    private string $dir = '';

    public function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/cvx-' . bin2hex(random_bytes(4));
        @mkdir($this->dir);
    }

    public function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->dir);
    }

    public function test_extracts_text_from_a_docx(): void
    {
        $path = $this->dir . '/cv.docx';
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE);
        $zip->addFromString(
            'word/document.xml',
            '<?xml version="1.0"?><w:document xmlns:w="x"><w:body>'
            . '<w:p><w:r><w:t>Jane Engineer</w:t></w:r></w:p>'
            . '<w:p><w:r><w:t>Senior PHP Developer based in Cairo</w:t></w:r></w:p>'
            . '<w:p><w:r><w:t>Eight years building Laravel and MySQL platforms.</w:t></w:r></w:p>'
            . '</w:body></w:document>'
        );
        $zip->close();

        $out = (new CvExtractor())->extract($path, 'cv.docx');
        $this->assertTrue(str_contains($out['text'], 'Jane Engineer'));
        $this->assertTrue(str_contains($out['text'], 'Senior PHP Developer'));
        $this->assertTrue($out['confident']);
    }

    public function test_extracts_text_from_rtf(): void
    {
        $path = $this->dir . '/cv.rtf';
        file_put_contents($path, '{\\rtf1\\ansi John Doe\\par Backend Engineer with 8 years experience\\par}');

        $out = (new CvExtractor())->extract($path, 'cv.rtf');
        $this->assertTrue(str_contains($out['text'], 'John Doe'));
        $this->assertTrue(str_contains($out['text'], 'Backend Engineer'));
    }

    public function test_extracts_plain_text(): void
    {
        $path = $this->dir . '/cv.txt';
        file_put_contents($path, "Sara Ali\nsara@example.com\nProduct Manager with ten years of experience leading cross-functional teams.");

        $out = (new CvExtractor())->extract($path, 'cv.txt');
        $this->assertTrue(str_contains($out['text'], 'Sara Ali'));
        $this->assertTrue(str_contains($out['text'], 'sara@example.com'));
        $this->assertTrue($out['confident']);
    }

    public function test_binary_noise_is_low_confidence(): void
    {
        $path = $this->dir . '/cv.bin';
        file_put_contents($path, random_bytes(400));

        $out = (new CvExtractor())->extract($path, 'cv.bin');
        $this->assertFalse($out['confident']);
    }
};
