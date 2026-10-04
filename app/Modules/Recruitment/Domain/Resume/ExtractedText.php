<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Recruitment\Domain\Resume;

/**
 * The output of a single parser: normalised plain text + how confident we are
 * that the extraction is faithful (0 = failed/garbage, 100 = clean text source).
 * Pure value object.
 */
final class ExtractedText
{
    public function __construct(
        public readonly string $text,
        public readonly int $confidence,
        public readonly string $parser,
    ) {
    }

    public static function empty(string $parser): self
    {
        return new self('', 0, $parser);
    }

    public function isUsable(): bool
    {
        return trim($this->text) !== '' && $this->confidence > 0;
    }
}
