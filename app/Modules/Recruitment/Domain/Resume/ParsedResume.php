<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Recruitment\Domain\Resume;

/**
 * The structured projection of a résumé — produced by the Resume Parsing Layer
 * and consumed by the Resume Analysis Engine. It carries BOTH the normalised raw
 * text and every field we could mechanically extract from it.
 *
 * The Analysis Engine never sees a file: it sees this. Where structuring fails
 * for a field, the field is empty and the engine falls back to the raw text
 * (docs/FIRST_IMPRESSION_ENGINE.md §2). Pure value object — no I/O.
 */
final class ParsedResume
{
    /**
     * @param  list<string>  $links
     * @param  list<string>  $jobTitles
     * @param  list<string>  $companies
     * @param  list<array{title?: string, company?: string, start?: ?string, end?: ?string, months?: ?int}>  $experiences
     * @param  list<string>  $skills
     * @param  list<string>  $education
     * @param  list<string>  $certifications
     * @param  list<string>  $languages
     * @param  list<string>  $projects
     * @param  list<string>  $publications
     * @param  list<string>  $awards
     */
    public function __construct(
        public readonly string $rawText,
        public readonly int $parseConfidence,
        public readonly string $parser,
        public readonly ?string $name = null,
        public readonly ?string $email = null,
        public readonly ?string $phone = null,
        public readonly ?string $address = null,
        public readonly ?string $summary = null,
        public readonly ?int $yearsExperience = null,
        public readonly array $links = [],
        public readonly array $jobTitles = [],
        public readonly array $companies = [],
        public readonly array $experiences = [],
        public readonly array $skills = [],
        public readonly array $education = [],
        public readonly array $certifications = [],
        public readonly array $languages = [],
        public readonly array $projects = [],
        public readonly array $publications = [],
        public readonly array $awards = [],
    ) {
    }

    /** The canonical résumé section keys we try to detect (for completeness). */
    public const SECTIONS = [
        'contact', 'summary', 'experience', 'education', 'skills',
        'certifications', 'languages', 'projects',
    ];

    /** Which canonical sections were detected (non-empty). @return list<string> */
    public function presentSections(): array
    {
        $present = [];
        if ($this->email !== null || $this->phone !== null) {
            $present[] = 'contact';
        }
        if ($this->summary !== null && $this->summary !== '') {
            $present[] = 'summary';
        }
        if ($this->experiences !== [] || $this->jobTitles !== []) {
            $present[] = 'experience';
        }
        if ($this->education !== []) {
            $present[] = 'education';
        }
        if ($this->skills !== []) {
            $present[] = 'skills';
        }
        if ($this->certifications !== []) {
            $present[] = 'certifications';
        }
        if ($this->languages !== []) {
            $present[] = 'languages';
        }
        if ($this->projects !== []) {
            $present[] = 'projects';
        }

        return $present;
    }

    /** Which canonical sections are missing. @return list<string> */
    public function missingSections(): array
    {
        return array_values(array_diff(self::SECTIONS, $this->presentSections()));
    }

    /** Lower-cased haystack used as a last-resort fallback for matching. */
    public function searchableText(): string
    {
        $parts = [
            $this->rawText,
            implode(' ', $this->skills),
            implode(' ', $this->jobTitles),
            implode(' ', $this->education),
            implode(' ', $this->certifications),
            implode(' ', $this->languages),
            (string) $this->summary,
        ];

        return mb_strtolower(trim(implode("\n", array_filter($parts, static fn ($p): bool => trim((string) $p) !== ''))));
    }
}
