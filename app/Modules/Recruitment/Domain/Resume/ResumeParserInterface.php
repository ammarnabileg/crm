<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Recruitment\Domain\Resume;

/**
 * One résumé text extractor for a family of formats. The Resume Parsing Layer is
 * a registry of these (PDF / DOCX / TXT / …); adding a new format means adding a
 * new implementation — nothing else changes (docs/FIRST_IMPRESSION_ENGINE.md §2).
 *
 * A parser is responsible for ONE thing: turning bytes into normalised plain
 * text + a confidence. It MUST NOT throw on a malformed file — it returns the
 * best text it can with a low confidence, so the engine degrades gracefully and
 * the platform never becomes fragile.
 */
interface ResumeParserInterface
{
    /** Stable parser key, e.g. "pdf", "docx", "txt". */
    public function key(): string;

    /** Can this parser handle the given file (by extension + mime)? */
    public function supports(string $extension, string $mime): bool;

    /**
     * Extract normalised plain text from the file at $path.
     *
     * @return ExtractedText  text + confidence (0..100); empty text + 0 on failure
     */
    public function extract(string $path): ExtractedText;
}
