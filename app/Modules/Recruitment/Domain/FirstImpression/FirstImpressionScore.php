<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Recruitment\Domain\FirstImpression;

/**
 * The final First Impression Credibility Score and the gate decision. It binds
 * the two engines together with the agreed weighting:
 *
 *     core   = résumé/job-fit score          (Engine 1 — THE BASIS, 70%)
 *     social = bounded boost ∈ [-30, +30]     (Engine 2 — optional 30% helper)
 *     overall = clamp(core + social, 0, 100)
 *
 * A candidate with no social presence has social = 0, so overall == core: they
 * are never penalised. `passed` is overall ≥ the job's minimum threshold.
 *
 * Pure value object + a single pure factory. No I/O.
 */
final class FirstImpressionScore
{
    public function __construct(
        public readonly int $overall,
        public readonly int $core,
        public readonly int $resumeScore,
        public readonly int $jobMatchScore,
        public readonly ?int $socialScore,
        public readonly int $socialBoost,
        public readonly int $threshold,
        public readonly bool $passed,
        public readonly int $confidence,
    ) {
    }

    /**
     * @param  array{social_score: ?int, boost: int, scored_sources: int}  $socialRoll
     */
    public static function decide(
        ResumeAnalysisResult $resume,
        array $socialRoll,
        int $threshold,
        int $socialConfidence = 0,
    ): self {
        $core = $resume->resumeScore;
        $boost = (int) ($socialRoll['boost'] ?? 0);
        $overall = max(0, min(100, $core + $boost));

        $confidence = ($socialRoll['social_score'] ?? null) !== null
            ? (int) round(0.8 * $resume->confidence + 0.2 * $socialConfidence)
            : $resume->confidence;

        $threshold = max(0, min(100, $threshold));

        return new self(
            overall: $overall,
            core: $core,
            resumeScore: $resume->resumeScore,
            jobMatchScore: $resume->jobMatchScore,
            socialScore: $socialRoll['social_score'] ?? null,
            socialBoost: $boost,
            threshold: $threshold,
            passed: $overall >= $threshold,
            confidence: $confidence,
        );
    }

    public function decision(): string
    {
        return $this->passed ? 'passed' : 'filtered';
    }
}
