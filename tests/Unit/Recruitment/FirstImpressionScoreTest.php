<?php

declare(strict_types=1);

namespace HaHireAI\Tests\Unit\Recruitment;

use HaHireAI\Modules\Recruitment\Domain\FirstImpression\FirstImpressionScore;
use HaHireAI\Modules\Recruitment\Domain\FirstImpression\ResumeAnalysisResult;
use PHPUnit\Framework\TestCase;

/** The combiner: core (CV/job) + optional social boost → decision. */
final class FirstImpressionScoreTest extends TestCase
{
    private function fixture(int $resumeScore): ResumeAnalysisResult
    {
        return new ResumeAnalysisResult(
            resumeScore: $resumeScore, jobMatchScore: $resumeScore, qualityScore: $resumeScore,
            confidence: 90, yearsExperience: 8, subScores: [],
        );
    }

    public function test_no_social_means_overall_equals_core(): void
    {
        $fi = FirstImpressionScore::decide($this->fixture(72), ['social_score' => null, 'boost' => 0, 'scored_sources' => 0], 65);
        $this->assertSame(72, $fi->overall);
        $this->assertSame(72, $fi->core);
        $this->assertTrue($fi->passed);
        $this->assertNull($fi->socialScore);
        $this->assertSame(90, $fi->confidence, 'confidence is the résumé confidence when no social');
    }

    public function test_candidate_without_social_is_never_penalised_below_threshold(): void
    {
        // Exactly on the threshold with zero social must still pass.
        $fi = FirstImpressionScore::decide($this->fixture(65), ['social_score' => null, 'boost' => 0, 'scored_sources' => 0], 65);
        $this->assertTrue($fi->passed);
    }

    public function test_positive_social_boost_raises_overall_and_is_clamped(): void
    {
        $fi = FirstImpressionScore::decide($this->fixture(90), ['social_score' => 80, 'boost' => 24, 'scored_sources' => 1], 65, 70);
        $this->assertSame(100, $fi->overall);   // 90 + 24 clamped to 100
        $this->assertSame(24, $fi->socialBoost);
    }

    public function test_negative_social_lowers_overall_but_keeps_core(): void
    {
        $fi = FirstImpressionScore::decide($this->fixture(70), ['social_score' => -40, 'boost' => -12, 'scored_sources' => 1], 65, 35);
        $this->assertSame(58, $fi->overall);    // 70 - 12
        $this->assertSame(70, $fi->core);
        $this->assertFalse($fi->passed);        // 58 < 65 → filtered
        $this->assertSame('filtered', $fi->decision());
    }

    public function test_decision_string(): void
    {
        $this->assertSame('passed', FirstImpressionScore::decide($this->fixture(80), ['social_score' => null, 'boost' => 0, 'scored_sources' => 0], 65)->decision());
        $this->assertSame('filtered', FirstImpressionScore::decide($this->fixture(40), ['social_score' => null, 'boost' => 0, 'scored_sources' => 0], 65)->decision());
    }
}
