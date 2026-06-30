<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Recruitment\Domain\FirstImpression;

use HaHireAI\Modules\Recruitment\Domain\SkillOntology;

/**
 * The job's requirements, distilled from a `jobs` row into exactly what the
 * Resume Analysis Engine needs to measure "closeness to the job". Pure value
 * object; built once per analysis. Never touches the database.
 */
final class JobProfile
{
    /**
     * @param  list<string>  $requiredSkills  canonicalised
     * @param  list<string>  $keywords        HR screening keywords (lower-cased)
     */
    public function __construct(
        public readonly string $title,
        public readonly ?string $seniority,
        public readonly array $requiredSkills,
        public readonly array $keywords,
        public readonly ?int $experienceMin,
        public readonly ?int $experienceMax,
        public readonly ?string $location = null,
        public readonly ?string $description = null,
    ) {
    }

    /** Build from a raw jobs row (the per-job configuration columns). */
    public static function fromJobRow(array $job): self
    {
        return new self(
            title: trim((string) ($job['title'] ?? '')),
            seniority: isset($job['seniority']) ? (string) $job['seniority'] : null,
            requiredSkills: self::canonicalTerms((string) ($job['required_skills'] ?? '')),
            keywords: self::splitTerms((string) ($job['screening_keywords'] ?? '')),
            experienceMin: isset($job['experience_min']) && $job['experience_min'] !== null ? (int) $job['experience_min'] : null,
            experienceMax: isset($job['experience_max']) && $job['experience_max'] !== null ? (int) $job['experience_max'] : null,
            location: isset($job['location']) ? (string) $job['location'] : null,
            description: isset($job['description']) ? (string) $job['description'] : null,
        );
    }

    /** All terms that matter for relevance: required skills + keywords + title tokens. */
    public function relevanceTerms(): array
    {
        $terms = array_merge($this->requiredSkills, $this->keywords);

        return array_values(array_unique(array_filter($terms, static fn (string $t): bool => trim($t) !== '')));
    }

    public function seniorityRank(): int
    {
        return SkillOntology::jobSeniorityRank($this->seniority);
    }

    /** @return list<string> */
    private static function canonicalTerms(string $raw): array
    {
        $out = [];
        foreach (self::splitTerms($raw) as $term) {
            $canonical = SkillOntology::canonical($term);
            if ($canonical !== '') {
                $out[$canonical] = true;
            }
        }

        return array_keys($out);
    }

    /** @return list<string> */
    private static function splitTerms(string $raw): array
    {
        if (trim($raw) === '') {
            return [];
        }
        $out = [];
        foreach (preg_split('/[,;\r\n|]+/', $raw) ?: [] as $part) {
            $term = trim((string) $part);
            if ($term !== '') {
                $out[$term] = true;
            }
        }

        return array_keys($out);
    }
}
