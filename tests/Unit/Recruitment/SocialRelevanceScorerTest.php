<?php

declare(strict_types=1);

namespace HaHireAI\Tests\Unit\Recruitment;

use HaHireAI\Modules\Recruitment\Domain\FirstImpression\JobProfile;
use HaHireAI\Modules\Recruitment\Domain\FirstImpression\SocialRelevanceScorer;
use PHPUnit\Framework\TestCase;

/** Scoring a social snapshot's relevance to a job (provider-agnostic). */
final class SocialRelevanceScorerTest extends TestCase
{
    private function github(array $overrides = []): array
    {
        return array_merge([
            'platform' => 'github', 'url' => 'https://github.com/x', 'reachable' => true, 'fetched' => true,
            'footprint_strength' => 80, 'relevance_text' => 'PHP Laravel API toolkit', 'skills' => ['PHP', 'JavaScript'],
            'signals' => [],
        ], $overrides);
    }

    public function test_unreachable_snapshot_is_neutral_null(): void
    {
        $job = JobProfile::fromJobRow(['title' => 'PHP Engineer', 'required_skills' => 'PHP']);
        $v = SocialRelevanceScorer::score(['platform' => 'linkedin', 'url' => 'u', 'reachable' => false, 'footprint_strength' => null, 'relevance_text' => '', 'skills' => []], $job);
        $this->assertNull($v['score']);
        $this->assertFalse($v['reachable']);
    }

    public function test_relevant_footprint_scores_positive(): void
    {
        $job = JobProfile::fromJobRow(['title' => 'Senior PHP Engineer', 'required_skills' => 'PHP,Laravel,MySQL']);
        $v = SocialRelevanceScorer::score($this->github(), $job);
        $this->assertGreaterThan(0, $v['score']);
        $this->assertContains('PHP', $v['matched']);
        $this->assertContains('Laravel', $v['matched']);
    }

    public function test_off_target_footprint_scores_negative(): void
    {
        $job = JobProfile::fromJobRow(['title' => 'Head Chef', 'required_skills' => 'Cooking,Pastry']);
        $v = SocialRelevanceScorer::score($this->github(), $job);
        $this->assertLessThan(0, $v['score']);
        $this->assertSame([], $v['matched']);
    }

    public function test_reachable_but_thin_footprint_is_neutral_zero(): void
    {
        $job = JobProfile::fromJobRow(['title' => 'Head Chef', 'required_skills' => 'Cooking']);
        $thin = ['platform' => 'website', 'url' => 'u', 'reachable' => true, 'fetched' => true, 'footprint_strength' => 10, 'relevance_text' => 'hello world', 'skills' => []];
        $v = SocialRelevanceScorer::score($thin, $job);
        $this->assertSame(0, $v['score']);
    }

    public function test_score_is_bounded(): void
    {
        $job = JobProfile::fromJobRow(['title' => 'PHP Engineer', 'required_skills' => 'PHP,Laravel']);
        $v = SocialRelevanceScorer::score($this->github(['footprint_strength' => 100]), $job);
        $this->assertLessThanOrEqual(100, $v['score']);
        $this->assertGreaterThanOrEqual(-100, $v['score']);
    }
}
