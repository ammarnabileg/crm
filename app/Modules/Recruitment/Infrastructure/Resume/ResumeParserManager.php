<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Recruitment\Infrastructure\Resume;

use HaHireAI\Modules\Recruitment\Domain\Resume\ExtractedText;
use HaHireAI\Modules\Recruitment\Domain\Resume\ResumeParserInterface;

/**
 * The Resume Parsing Layer entry point: a registry of format parsers. It selects
 * the right parser for an uploaded file and returns normalised text — the
 * Analysis Engine downstream never learns the file type. Registering a new
 * format (e.g. ODT) is a one-line addition; no other code changes
 * (docs/FIRST_IMPRESSION_ENGINE.md §2).
 */
final class ResumeParserManager
{
    /** @var list<ResumeParserInterface> */
    private array $parsers = [];

    /** @param iterable<ResumeParserInterface> $parsers */
    public function __construct(iterable $parsers = [])
    {
        foreach ($parsers as $parser) {
            $this->register($parser);
        }
        if ($this->parsers === []) {
            $this->registerDefaults();
        }
    }

    public function register(ResumeParserInterface $parser): void
    {
        $this->parsers[] = $parser;
    }

    private function registerDefaults(): void
    {
        $this->parsers = [
            new PdfResumeParser(),
            new DocxResumeParser(),
            new TxtResumeParser(),
        ];
    }

    /**
     * Extract text from a file, choosing a parser by extension/mime. Falls back
     * to a raw printable-text recovery so even an unknown format yields
     * something (low confidence) rather than nothing.
     */
    public function extract(string $path, string $originalName, string $mime = ''): ExtractedText
    {
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        foreach ($this->parsers as $parser) {
            if ($parser->supports($ext, $mime)) {
                $result = $parser->extract($path);
                if ($result->isUsable()) {
                    return $result;
                }
                break; // matched the format but couldn't read it — try generic recovery
            }
        }

        return $this->genericRecovery($path);
    }

    /** Last-resort: strip a binary blob to its printable runs (e.g. legacy .doc). */
    private function genericRecovery(string $path): ExtractedText
    {
        if (! is_file($path)) {
            return ExtractedText::empty('none');
        }
        $bytes = @file_get_contents($path);
        if ($bytes === false || $bytes === '') {
            return ExtractedText::empty('none');
        }

        // Keep printable ASCII + newlines; collapse the rest.
        $printable = preg_replace('/[^\P{C}\n]+/u', ' ', $bytes) ?? $bytes;
        $printable = preg_replace('/[^\x20-\x7E\n\xA0-\xFF]/u', ' ', $printable) ?? $printable;
        $text = TextNormalizer::normalize($printable);

        // Require a minimum signal so we don't return binary noise.
        return (mb_strlen($text) >= 40 && preg_match('/[a-zA-Z]{3,}/', $text))
            ? new ExtractedText($text, 25, 'generic')
            : ExtractedText::empty('none');
    }

    /** @return list<string> registered parser keys */
    public function keys(): array
    {
        return array_map(static fn (ResumeParserInterface $p): string => $p->key(), $this->parsers);
    }
}
