<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Recruitment\Domain\FirstImpression;

/**
 * Engine 2 — the Social Credibility SCORER (the rule math; the data collection
 * lives in the Integration Platform adapters, never here). It turns each social
 * source's relevance evaluation into a single, bounded, NEUTRAL-by-default
 * number.
 *
 * The product rules (verbatim from the spec discussion):
 *   - Social is an OPTIONAL helper, never the basis. The basis is résumé/job fit.
 *   - A candidate with NO social accounts is NEVER penalised.
 *   - Per source: baseline 0; goes UP (→ +100) with useful, JOB-RELEVANT info;
 *     goes DOWN (→ -100) when the footprint is clearly OFF-target, contradictory,
 *     or broken. A link with NO information stays at 0 (neutral — no effect).
 *   - The social score is the AVERAGE of the per-source scores.
 *   - It is worth 30% of the headline, applied as a bounded boost so neutral /
 *     absent social shifts the score by exactly ZERO.
 *
 * Pure, deterministic, unit-testable.
 */
final class SocialScoring
{
    /** Social's share of the headline: ±30 points around the résumé/job core. */
    public const MAX_BOOST = 30;

    /**
     * Combine per-source relevance scores into the raw social score (-100..100)
     * and the bounded boost (-30..30) that is added to the core.
     *
     * Sources that produced NO signal (score === null) are excluded from the
     * average — they are neutral, never a drag.
     *
     * @param  list<array{score: ?int, reachable?: bool}>  $sources
     * @return array{social_score: ?int, boost: int, scored_sources: int}
     */
    public static function roll(array $sources): array
    {
        $scored = [];
        foreach ($sources as $s) {
            if (array_key_exists('score', $s) && $s['score'] !== null) {
                $scored[] = self::clamp((int) $s['score'], -100, 100);
            }
        }

        if ($scored === []) {
            // No usable social signal anywhere → strictly neutral, no penalty.
            return ['social_score' => null, 'boost' => 0, 'scored_sources' => 0];
        }

        $avg = (int) round(array_sum($scored) / count($scored));
        $boost = (int) round(self::MAX_BOOST * ($avg / 100));

        return [
            'social_score' => $avg,
            'boost' => self::clamp($boost, -self::MAX_BOOST, self::MAX_BOOST),
            'scored_sources' => count($scored),
        ];
    }

    /**
     * Confidence in the social verdict: more reachable, signal-bearing sources →
     * higher confidence. No sources → 0 (and boost is 0 anyway).
     *
     * @param  list<array{score: ?int, reachable?: bool}>  $sources
     */
    public static function confidence(array $sources): int
    {
        $reachableWithSignal = 0;
        foreach ($sources as $s) {
            if (($s['reachable'] ?? false) && array_key_exists('score', $s) && $s['score'] !== null) {
                $reachableWithSignal++;
            }
        }

        return self::clamp($reachableWithSignal * 35, 0, 100);
    }

    private static function clamp(int $v, int $min, int $max): int
    {
        return max($min, min($max, $v));
    }
}
