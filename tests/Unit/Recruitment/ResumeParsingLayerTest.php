<?php

declare(strict_types=1);

namespace HaHireAI\Tests\Unit\Recruitment;

use HaHireAI\Modules\Recruitment\Domain\Resume\ResumeStructurer;
use HaHireAI\Modules\Recruitment\Infrastructure\Resume\DocxResumeParser;
use HaHireAI\Modules\Recruitment\Infrastructure\Resume\ResumeParserManager;
use HaHireAI\Modules\Recruitment\Infrastructure\Resume\TxtResumeParser;
use PHPUnit\Framework\TestCase;
use ZipArchive;

/** The format-agnostic résumé parsing layer + structuring. */
final class ResumeParsingLayerTest extends TestCase
{
    private const CV = "Sara Hassan\nSenior Software Engineer\n"
        . "Email: sara.hassan@example.com | Phone: +20 100 123 4567\nCairo, Egypt\n"
        . "GitHub: https://github.com/sarah\n"
        . "Summary\n8+ years building PHP/Laravel platforms.\n"
        . "Experience\nSenior Software Engineer at Acme Corp (2018 - Present)\n"
        . "Skills\nPHP, Laravel, MySQL, JavaScript, Docker, k8s\n"
        . "Education\nBSc Computer Science, Cairo University\n"
        . "Languages\nArabic, English";

    public function test_manager_routes_txt_and_structurer_extracts_fields(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'cv') . '.txt';
        file_put_contents($path, self::CV);

        $extracted = (new ResumeParserManager())->extract($path, 'sara.txt', 'text/plain');
        @unlink($path);

        $this->assertSame('txt', $extracted->parser);
        $this->assertSame(100, $extracted->confidence);

        $p = (new ResumeStructurer())->structure($extracted);
        $this->assertSame('Sara Hassan', $p->name);
        $this->assertSame('sara.hassan@example.com', $p->email);
        $this->assertNotNull($p->phone);
        $this->assertSame(8, $p->yearsExperience);
        $this->assertContains('PHP', $p->skills);
        $this->assertContains('Kubernetes', $p->skills);          // via "k8s"
        $this->assertContains('Acme Corp', $p->companies);
        $this->assertContains('Arabic', $p->languages);
        $this->assertContains('https://github.com/sarah', $p->links);
        $this->assertContains('skills', $p->presentSections());
        $this->assertContains('projects', $p->missingSections()); // none present
    }

    public function test_parser_keys_cover_pdf_docx_txt(): void
    {
        $this->assertSame(['pdf', 'docx', 'txt'], (new ResumeParserManager())->keys());
    }

    public function test_docx_parser_reads_native_zip_xml(): void
    {
        if (! class_exists(ZipArchive::class)) {
            $this->markTestSkipped('ext-zip not available');
        }
        $path = tempnam(sys_get_temp_dir(), 'cv') . '.docx';
        $this->writeMinimalDocx($path, 'Sara Hassan — Senior PHP Engineer. Skills: Laravel, MySQL.');

        $extracted = (new DocxResumeParser())->extract($path);
        @unlink($path);

        $this->assertTrue($extracted->isUsable());
        $this->assertStringContainsString('Senior PHP Engineer', $extracted->text);
        $this->assertStringContainsString('Laravel', $extracted->text);
    }

    public function test_txt_parser_supports_matching(): void
    {
        $p = new TxtResumeParser();
        $this->assertTrue($p->supports('txt', 'text/plain'));
        $this->assertTrue($p->supports('', 'text/markdown'));
        $this->assertFalse($p->supports('pdf', 'application/pdf'));
    }

    public function test_unreadable_file_degrades_to_empty_not_an_exception(): void
    {
        $extracted = (new ResumeParserManager())->extract('/no/such/file.pdf', 'x.pdf', 'application/pdf');
        $this->assertFalse($extracted->isUsable());
    }

    private function writeMinimalDocx(string $path, string $text): void
    {
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="xml" ContentType="application/xml"/></Types>');
        $doc = '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>'
            . htmlspecialchars($text, ENT_XML1) . '</w:t></w:r></w:p></w:body></w:document>';
        $zip->addFromString('word/document.xml', $doc);
        $zip->close();
    }
}
