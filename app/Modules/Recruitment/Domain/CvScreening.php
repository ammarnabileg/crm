<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Recruitment\Domain;

/**
 * Deterministic, NO-AI pre-screen. Turns the applicant's own words (CV summary,
 * structured skills/education, cover note) into text and checks them against the
 * keywords an HR user defined on the job, so the (paid) AI interview only runs
 * for plausibly-relevant applicants and AI credits are not wasted. Pure functions
 * — no I/O, no provider, fully unit-testable.
 */
final class CvScreening
{
    /**
     * Parse a comma / semicolon / newline separated keyword list into distinct,
     * trimmed, lower-cased terms.
     *
     * @return list<string>
     */
    public static function keywords(?string $raw): array
    {
        if ($raw === null || trim($raw) === '') {
            return [];
        }
        $out = [];
        foreach (preg_split('/[,;\r\n]+/', $raw) ?: [] as $part) {
            $term = mb_strtolower(trim((string) $part));
            if ($term !== '') {
                $out[$term] = true;
            }
        }

        return array_keys($out);
    }

    /**
     * The keywords that appear in the candidate's text (case-insensitive).
     *
     * @param  list<string>  $keywords
     * @return list<string>
     */
    public static function hits(string $candidateText, array $keywords): array
    {
        $hay = mb_strtolower($candidateText);
        $hits = [];
        foreach ($keywords as $kw) {
            $term = mb_strtolower(trim((string) $kw));
            if ($term !== '' && mb_strpos($hay, $term) !== false) {
                $hits[$term] = true;
            }
        }

        return array_keys($hits);
    }

    /**
     * Does the candidate clear the keyword pre-screen? With no keywords defined
     * the gate is OPEN (true). Otherwise at least $minHits keyword(s) must match.
     *
     * @param  list<string>  $keywords
     */
    public static function passes(string $candidateText, array $keywords, int $minHits = 1): bool
    {
        if ($keywords === []) {
            return true;
        }

        return count(self::hits($candidateText, $keywords)) >= max(1, $minHits);
    }
}
