<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Recruitment\Domain\FirstImpression;

use HaHireAI\Modules\Recruitment\Domain\SkillOntology;

/**
 * Scores ONE social snapshot's relevance to the job, on the agreed scale:
 *
 *   null  → no usable signal (unreachable / empty / login-walled) — NEUTRAL,
 *           excluded from the average. A candidate is NEVER penalised for this.
 *   > 0   → the public footprint is RELEVANT to the job (overlapping skills /
 *           technical text), scaled by how substantial the footprint is.
 *   < 0   → the footprint clearly exists but is OFF-TARGET (skills detected, none
 *           relevant to the job) — a bounded, mild negative.
 *
 * This lives in Recruitment because only Recruitment knows the job. It is
 * provider-AGNOSTIC: it reads the normalised snapshot envelope, never a specific
 * source. Pure, deterministic, unit-testable.
 */
final class SocialRelevanceScorer
{
    /**
     * @param  array<string, mixed>  $snapshot  the SocialProfileProbe envelope
     * @return array{score: ?int, reachable: bool, matched: list<string>}
     */
    public static function score(array $snapshot, JobProfile $job): array
    {
        $reachable = (bool) ($snapshot['reachable'] ?? false);
        $footprint = $snapshot['footprint_strength'] ?? null;
        $text = (string) ($snapshot['relevance_text'] ?? '');
        $rawSkills = array_map('strval', (array) ($snapshot['skills'] ?? []));

        // No usable signal at all → strictly neutral (null), excluded from average.
        if (! $reachable || ($footprint === null && trim($text) === '' && $rawSkills === [])) {
            return ['score' => null, 'reachable' => $reachable, 'matched' => []];
        }

        // Canonical skills the footprint demonstrates (declared + detected in text).
        $footprintSkills = [];
        foreach ($rawSkills as $s) {
            $c = SkillOntology::canonical($s);
            if ($c !== '') {
                $footprintSkills[$c] = true;
            }
        }
        foreach (SkillOntology::detectSkills($text) as $s) {
            $footprintSkills[$s] = true;
        }
        $footprintSkills = array_keys($footprintSkills);

        // Job relevance terms (canonical skills + keywords).
        $jobTerms = [];
        foreach ($job->relevanceTerms() as $t) {
            $c = SkillOntology::canonical($t);
            if ($c !== '') {
                $jobTerms[$c] = true;
            }
        }
        // Also reward overlap with the job title's meaningful tokens.
        foreach (self::titleTokens($job->title) as $tok) {
            if (mb_strpos(mb_strtolower($text), $tok) !== false) {
                $jobTerms[ucfirst($tok)] = true;
            }
        }
        $jobTerms = array_keys($jobTerms);

        $matched = array_values(array_intersect($footprintSkills, $jobTerms));
        $mag = $footprint !== null ? (int) $footprint : self::magnitudeFromSkills(count($footprintSkills));

        // Direct keyword/title relevance in free text (even without skill tags).
        $textRelevant = self::textMentionsJob($text, $job);

        if ($matched !== [] || $textRelevant) {
            $overlapRatio = $jobTerms !== [] ? count($matched) / max(1, count($jobTerms)) : 0.0;
            $relevance = max($overlapRatio, $textRelevant ? 0.34 : 0.0); // a text hit is meaningful on its own
            $score = (int) round(min(100, $relevance * 100 * (0.55 + 0.45 * $mag / 100)));

            return ['score' => max(1, $score), 'reachable' => true, 'matched' => $matched];
        }

        // A substantial footprint with NO relevance to the job → mild negative.
        if ($footprintSkills !== [] && $mag >= 25) {
            $score = -1 * (int) round(min(70, $mag * 0.7));

            return ['score' => $score, 'reachable' => true, 'matched' => []];
        }

        // Reachable but thin/ambiguous → genuinely neutral.
        return ['score' => 0, 'reachable' => true, 'matched' => []];
    }

    private static function magnitudeFromSkills(int $count): int
    {
        return min(100, $count * 14);
    }

    private static function textMentionsJob(string $text, JobProfile $job): bool
    {
        $hay = mb_strtolower($text);
        foreach ($job->relevanceTerms() as $term) {
            if (SkillOntology::skillPresent($term, $hay)) {
                return true;
            }
        }
        foreach (self::titleTokens($job->title) as $tok) {
            if (mb_strpos($hay, $tok) !== false) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> meaningful lower-cased tokens from a job title */
    private static function titleTokens(string $title): array
    {
        $stop = ['senior', 'junior', 'lead', 'principal', 'staff', 'mid', 'level', 'the', 'and', 'of', 'for', 'a', 'an'];
        $tokens = [];
        foreach (preg_split('/[^a-z0-9+#.]+/i', mb_strtolower($title)) ?: [] as $tok) {
            if (mb_strlen($tok) >= 3 && ! in_array($tok, $stop, true)) {
                $tokens[$tok] = true;
            }
        }

        return array_keys($tokens);
    }
}
