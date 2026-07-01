<?php

declare(strict_types=1);

namespace Nizam\Tests\Unit\Runtime\Execution;

use Nizam\Runtime\Execution\Application\RetryEngine;
use Nizam\Runtime\Execution\Domain\ValueObject\BackoffStrategy;
use Nizam\Runtime\Execution\Domain\ValueObject\RetryPolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the {@see RetryEngine}: it honours the policy's retry budget, computes fixed and exponential
 * backoff correctly, and applies jitter within bounds using an injected, deterministic randomizer.
 */
#[CoversClass(RetryEngine::class)]
final class RetryEngineTest extends TestCase
{
    public function testRefusesToRetryOnceTheBudgetIsSpent(): void
    {
        $engine = new RetryEngine();
        $policy = new RetryPolicy(2, 100, BackoffStrategy::Fixed, false);

        // Two attempts already made against a two-attempt policy: no retry.
        $decision = $engine->decide($policy, 2);

        self::assertFalse($decision->shouldRetry());
        self::assertSame(0, $decision->delayMs());
        self::assertSame(2, $decision->attempt());
    }

    public function testPermitsRetryWhileWithinBudget(): void
    {
        $engine = new RetryEngine();
        $policy = new RetryPolicy(3, 100, BackoffStrategy::Fixed, false);

        $decision = $engine->decide($policy, 1);

        self::assertTrue($decision->shouldRetry());
        self::assertSame(2, $decision->attempt());
        self::assertSame(100, $decision->delayMs());
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function fixedBackoff(): iterable
    {
        yield 'first retry -> attempt 2' => [1, 100];
        yield 'second retry -> attempt 3' => [2, 100];
        yield 'third retry -> attempt 4' => [3, 100];
    }

    #[DataProvider('fixedBackoff')]
    public function testFixedBackoffIsConstant(int $attemptsMade, int $expectedDelay): void
    {
        $engine = new RetryEngine();
        $policy = new RetryPolicy(10, 100, BackoffStrategy::Fixed, false);

        self::assertSame($expectedDelay, $engine->decide($policy, $attemptsMade)->delayMs());
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function exponentialBackoff(): iterable
    {
        // nextDelayMs is 1-based on the attempt number the delay precedes:
        // attempt 2 => base * 2^0, attempt 3 => base * 2^1, attempt 4 => base * 2^2.
        yield 'made 1 -> next attempt 2 -> 100' => [1, 100];
        yield 'made 2 -> next attempt 3 -> 200' => [2, 200];
        yield 'made 3 -> next attempt 4 -> 400' => [3, 400];
        yield 'made 4 -> next attempt 5 -> 800' => [4, 800];
    }

    #[DataProvider('exponentialBackoff')]
    public function testExponentialBackoffDoubles(int $attemptsMade, int $expectedDelay): void
    {
        $engine = new RetryEngine();
        $policy = new RetryPolicy(20, 100, BackoffStrategy::Exponential, false);

        self::assertSame($expectedDelay, $engine->decide($policy, $attemptsMade)->delayMs());
    }

    public function testJitterIsBoundedByTheComputedDelayAndUsesTheInjectedRandomizer(): void
    {
        $captured = [];
        // A deterministic randomizer that records its bounds and returns the midpoint.
        $engine = new RetryEngine(function (int $min, int $max) use (&$captured): int {
            $captured = [$min, $max];

            return intdiv($min + $max, 2);
        });
        $policy = new RetryPolicy(10, 100, BackoffStrategy::Exponential, true);

        // made 2 => next attempt 3 => computed delay 200, jitter over [0, 200].
        $decision = $engine->decide($policy, 2);

        self::assertSame([0, 200], $captured);
        self::assertSame(100, $decision->delayMs());
        self::assertGreaterThanOrEqual(0, $decision->delayMs());
        self::assertLessThanOrEqual(200, $decision->delayMs());
    }

    public function testJitterLeavesAZeroDelayUntouchedWithoutCallingTheRandomizer(): void
    {
        $called = false;
        $engine = new RetryEngine(function (int $min, int $max) use (&$called): int {
            $called = true;

            return $max;
        });
        // Zero base delay: the computed backoff is 0, so jitter is a no-op.
        $policy = new RetryPolicy(10, 0, BackoffStrategy::Exponential, true);

        $decision = $engine->decide($policy, 1);

        self::assertSame(0, $decision->delayMs());
        self::assertFalse($called);
    }

    public function testDefaultRandomizerProducesADelayWithinBounds(): void
    {
        $engine = new RetryEngine();
        $policy = new RetryPolicy(10, 1000, BackoffStrategy::Fixed, true);

        $decision = $engine->decide($policy, 1);

        self::assertTrue($decision->shouldRetry());
        self::assertGreaterThanOrEqual(0, $decision->delayMs());
        self::assertLessThanOrEqual(1000, $decision->delayMs());
    }
}
