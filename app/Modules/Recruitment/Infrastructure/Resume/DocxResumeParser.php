<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Recruitment\Infrastructure\Resume;

use HaHireAI\Modules\Recruitment\Domain\Resume\ExtractedText;
use HaHireAI\Modules\Recruitment\Domain\Resume\ResumeParserInterface;
use ZipArchive;

/**
 * DOCX résumé reader. A .docx is a ZIP of XML parts; the visible text lives in
 * word/document.xml as <w:t> runs, with <w:p> paragraphs and <w:br>/<w:tab>
 * breaks. We read it NATIVELY with ZipArchive — no third-party library — so this
 * parser is always available (no fragile dependency). Headers/footers are merged
 * in as a bonus when present.
 */
final class DocxResumeParser implements ResumeParserInterface
{
    private const MIMES = [
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    public function key(): string
    {
        return 'docx';
    }

    public function supports(string $extension, string $mime): bool
    {
        return $extension === 'docx' || in_array($mime, self::MIMES, true);
    }

    public function extract(string $path): ExtractedText
    {
        if (! is_file($path) || ! class_exists(ZipArchive::class)) {
            return ExtractedText::empty($this->key());
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return ExtractedText::empty($this->key());
        }

        $xmlParts = [];
        foreach (['word/document.xml', 'word/header1.xml', 'word/header2.xml', 'word/footer1.xml', 'word/footer2.xml'] as $part) {
            $xml = $zip->getFromName($part);
            if (is_string($xml) && $xml !== '') {
                $xmlParts[] = $xml;
            }
        }
        $zip->close();

        if ($xmlParts === []) {
            return ExtractedText::empty($this->key());
        }

        $text = TextNormalizer::normalize(implode("\n", array_map([$this, 'xmlToText'], $xmlParts)));

        return $text === ''
            ? ExtractedText::empty($this->key())
            : new ExtractedText($text, 90, $this->key());
    }

    /** Turn WordprocessingML into plain text, honouring paragraph/line breaks. */
    private function xmlToText(string $xml): string
    {
        // Paragraph & line/tab boundaries become real whitespace BEFORE tag strip.
        $xml = preg_replace('#</w:p>#', "\n", $xml) ?? $xml;
        $xml = preg_replace('#<w:br\s*/?>#', "\n", $xml) ?? $xml;
        $xml = preg_replace('#<w:tab\s*/?>#', " \t", $xml) ?? $xml;

        // Keep only the text-run contents, then drop every remaining tag.
        $stripped = strip_tags($xml);

        return html_entity_decode($stripped, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
