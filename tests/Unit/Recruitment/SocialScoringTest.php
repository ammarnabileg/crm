<?php

declare(strict_types=1);

namespace HaHireAI\Tests\Unit\Recruitment;

use HaHireAI\Modules\Recruitment\Domain\FirstImpression\SocialScoring;
use PHPUnit\Framework\TestCase;

/** Engine 2 scoring math — the optional, absence-is-neutral social boost. */
final class SocialScoringTest extends TestCase
{
    public function test_no_sources_is_strictly_neutral(): void
    {
        $roll = SocialScoring::roll([]);
        $this->assertNull($roll['social_score']);
        $this->assertSame(0, $roll['boost']);
        $this->assertSame(0, $roll['scored_sources']);
    }

    public function test_null_score_sources_are_excluded_from_the_average(): void
    {
        // Two unreachable/empty links + one real positive → average is the real one.
        $roll = SocialScoring::roll([
            ['score' => null, 'reachable' => false],
            ['score' => null, 'reachable' => false],
            ['score' => 80, 'reachable' => true],
        ]);
        $this->assertSame(80, $roll['social_score']);
        $this->assertSame(1, $roll['scored_sources']);
        // 30% weight: boost = round(30 * 80/100) = 24.
        $this->assertSame(24, $roll['boost']);
    }

    public function test_boost_is_bounded_to_thirty_points_either_way(): void
    {
        $this->assertSame(SocialScoring::MAX_BOOST, SocialScoring::roll([['score' => 100, 'reachable' => true]])['boost']);
        $this->assertSame(-SocialScoring::MAX_BOOST, SocialScoring::roll([['score' => -100, 'reachable' => true]])['boost']);
    }

    public function test_average_of_mixed_relevance(): void
    {
        $roll = SocialScoring::roll([
            ['score' => 60, 'reachable' => true],
            ['score' => -20, 'reachable' => true],
        ]);
        $this->assertSame(20, $roll['social_score']);          // (60 - 20) / 2
        $this->assertSame(6, $roll['boost']);                   // round(30 * 20/100)
    }

    public function test_confidence_grows_with_signal_bearing_sources(): void
    {
        $this->assertSame(0, SocialScoring::confidence([]));
        $this->assertSame(0, SocialScoring::confidence([['score' => null, 'reachable' => false]]));
        $this->assertSame(35, SocialScoring::confidence([['score' => 50, 'reachable' => true]]));
        $this->assertSame(70, SocialScoring::confidence([
            ['score' => 50, 'reachable' => true],
            ['score' => 20, 'reachable' => true],
        ]));
    }
}
