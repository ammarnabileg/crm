<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Recruitment\Domain\FirstImpression;

/**
 * The output of the Resume Analysis Engine: the overall resume score, the
 * job-relevance score (the basis of the whole gate), every sub-score, and the
 * normalised, human-readable evidence behind them (matched/missing skills, rule
 * matches/failures, strengths, weaknesses, recommendations). Pure value object —
 * the persistence layer maps these into normalised rows, never a JSON blob.
 */
final class ResumeAnalysisResult
{
    /**
     * @param  array<string, int>  $subScores       sub-score key => 0..100
     * @param  list<string>  $matchedSkills
     * @param  list<string>  $missingSkills
     * @param  list<string>  $keywordHits
     * @param  list<string>  $strengths
     * @param  list<string>  $weaknesses
     * @param  list<string>  $recommendations
     * @param  list<string>  $ruleMatches
     * @param  list<string>  $ruleFailures
     * @param  list<string>  $missingSections
     */
    public function __construct(
        public readonly int $resumeScore,
        public readonly int $jobMatchScore,
        public readonly int $qualityScore,
        public readonly int $confidence,
        public readonly ?int $yearsExperience,
        public readonly array $subScores,
        public readonly array $matchedSkills = [],
        public readonly array $missingSkills = [],
        public readonly array $keywordHits = [],
        public readonly array $strengths = [],
        public readonly array $weaknesses = [],
        public readonly array $recommendations = [],
        public readonly array $ruleMatches = [],
        public readonly array $ruleFailures = [],
        public readonly array $missingSections = [],
    ) {
    }

    public function subScore(string $key): int
    {
        return $this->subScores[$key] ?? 0;
    }
}
