<?php

declare(strict_types=1);

namespace HaHireAI\Tests\Unit;

use HaHireAI\Modules\Recruitment\Domain\CandidateHealthScore;
use PHPUnit\Framework\TestCase;

/** The unified Candidate Health Score is a pure, weighted, re-normalising blend. */
final class CandidateHealthScoreTest extends TestCase
{
    public function test_all_components_present_is_a_plain_weighted_average(): void
    {
        // Every component = 80 → the blend is exactly 80 whatever the weights.
        $keys = ['job_match', 'resume_quality', 'experience', 'skills', 'learning',
            'certifications', 'interview_score', 'human_evaluation', 'social_credibility', 'activity'];
        $out = CandidateHealthScore::compute(array_fill_keys($keys, 80));

        $this->assertSame(80, $out['score']);
        $this->assertSame('Strong', $out['band']);
        // Weights of all contributing components sum to ~100.
        $sum = array_sum(array_map(static fn (array $c): int => $c['weight'], $out['components']));
        $this->assertSame(100, $sum);
    }

    public function test_job_match_is_the_heaviest_component(): void
    {
        $out = CandidateHealthScore::compute(array_fill_keys(
            ['job_match', 'resume_quality', 'experience', 'skills', 'learning',
                'certifications', 'interview_score', 'human_evaluation', 'social_credibility', 'activity'],
            50,
        ));
        $byKey = [];
        foreach ($out['components'] as $c) {
            $byKey[$c['key']] = $c['weight'];
        }

        $this->assertGreaterThan($byKey['social_credibility'], $byKey['job_match']);
        $this->assertSame(max($byKey), $byKey['job_match']); // strictly the largest
    }

    public function test_missing_components_are_dropped_and_weights_renormalise(): void
    {
        // Only job_match (100) and interview_score (0) are known → 28 vs 14 weights,
        // renormalised to 2:1 → (100*2 + 0*1)/3 = 66.7 → 67.
        $out = CandidateHealthScore::compute([
            'job_match' => 100,
            'interview_score' => 0,
        ]);

        $this->assertSame(67, $out['score']);
        foreach ($out['components'] as $c) {
            if (in_array($c['key'], ['job_match', 'interview_score'], true)) {
                $this->assertTrue($c['contributing']);
            } else {
                $this->assertFalse($c['contributing']);
                $this->assertNull($c['value']);
                $this->assertSame(0, $c['weight']);
            }
        }
    }

    public function test_no_signals_scores_zero_weak(): void
    {
        $out = CandidateHealthScore::compute([]);
        $this->assertSame(0, $out['score']);
        $this->assertSame('Weak', $out['band']);
    }

    public function test_values_are_clamped_into_range(): void
    {
        $out = CandidateHealthScore::compute(['job_match' => 150, 'skills' => -20]);
        // clamped to 100 and 0; weights 28:10 → (100*28 + 0*10)/38 = 73.7 → 74
        $this->assertSame(74, $out['score']);
    }

    public function test_bands(): void
    {
        $this->assertSame('Excellent', CandidateHealthScore::band(90));
        $this->assertSame('Strong', CandidateHealthScore::band(70));
        $this->assertSame('Moderate', CandidateHealthScore::band(55));
        $this->assertSame('Weak', CandidateHealthScore::band(30));
    }
}
