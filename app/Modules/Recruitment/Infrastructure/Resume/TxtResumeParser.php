<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Recruitment\Infrastructure\Resume;

use HaHireAI\Modules\Recruitment\Domain\Resume\ExtractedText;
use HaHireAI\Modules\Recruitment\Domain\Resume\ResumeParserInterface;

/**
 * Plain-text / RTF résumé reader. The simplest, most reliable source — text is
 * read as-is and normalised to UTF-8.
 */
final class TxtResumeParser implements ResumeParserInterface
{
    public function key(): string
    {
        return 'txt';
    }

    public function supports(string $extension, string $mime): bool
    {
        return in_array($extension, ['txt', 'text', 'md', 'rtf'], true)
            || str_starts_with($mime, 'text/');
    }

    public function extract(string $path): ExtractedText
    {
        if (! is_file($path)) {
            return ExtractedText::empty($this->key());
        }
        $bytes = @file_get_contents($path);
        if ($bytes === false || trim($bytes) === '') {
            return ExtractedText::empty($this->key());
        }

        $text = TextNormalizer::normalize($bytes);
        if (str_contains(mb_strtolower(substr($bytes, 0, 16)), '{\\rtf')) {
            $text = TextNormalizer::normalize(self::stripRtf($bytes));
        }

        return $text === ''
            ? ExtractedText::empty($this->key())
            : new ExtractedText($text, 100, $this->key());
    }

    /** Crude RTF control-word stripper — enough to recover the visible text. */
    private static function stripRtf(string $rtf): string
    {
        $rtf = preg_replace('/\\\\par[d]?/', "\n", $rtf) ?? $rtf;
        $rtf = preg_replace('/\{\\\\[^}]*\}/', ' ', $rtf) ?? $rtf;
        $rtf = preg_replace('/\\\\[a-zA-Z]+-?\d* ?/', ' ', $rtf) ?? $rtf;

        return str_replace(['{', '}'], ' ', $rtf);
    }
}
