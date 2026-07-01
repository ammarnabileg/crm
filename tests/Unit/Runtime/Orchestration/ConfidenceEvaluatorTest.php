<?php

declare(strict_types=1);

namespace Nizam\Tests\Unit\Runtime\Orchestration;

use Nizam\Runtime\Orchestration\ConfidenceEvaluator;
use Nizam\Runtime\Orchestration\ValueObject\MergedResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConfidenceEvaluator::class)]
final class ConfidenceEvaluatorTest extends TestCase
{
    private function merged(float $confidence, array $errors = []): MergedResult
    {
        return new MergedResult(
            results: [],
            evidence: [],
            recommendations: [],
            warnings: [],
            errors: $errors,
            aggregateConfidence: $confidence,
            totalTimeMs: 0,
            totalCostMicros: 0,
        );
    }

    public function testHighCleanConfidenceIsAcceptableWithNoFurtherWork(): void
    {
        $assessment = (new ConfidenceEvaluator())->evaluate($this->merged(0.9));

        self::assertSame(0.9, $assessment->score());
        self::assertFalse($assessment->needsRetry());
        self::assertFalse($assessment->needsMoreWorkers());
        self::assertTrue($assessment->isAcceptable(ConfidenceEvaluator::DEFAULT_ACCEPT_THRESHOLD));
    }

    public function testErrorsRequestRetryRegardlessOfScore(): void
    {
        $assessment = (new ConfidenceEvaluator())->evaluate($this->merged(0.95, ['boom']));

        self::assertTrue($assessment->needsRetry());
        self::assertFalse($assessment->needsMoreWorkers());
        self::assertFalse($assessment->isAcceptable(ConfidenceEvaluator::DEFAULT_ACCEPT_THRESHOLD));
    }

    public function testLowCleanConfidenceRequestsMoreWorkers(): void
    {
        $assessment = (new ConfidenceEvaluator())->evaluate($this->merged(0.3));

        self::assertFalse($assessment->needsRetry());
        self::assertTrue($assessment->needsMoreWorkers());
    }

    public function testMiddlingCleanConfidenceNeitherRetriesNorAddsWorkers(): void
    {
        $assessment = (new ConfidenceEvaluator())->evaluate($this->merged(0.6));

        self::assertFalse($assessment->needsRetry());
        self::assertFalse($assessment->needsMoreWorkers());
        self::assertFalse($assessment->isAcceptable(ConfidenceEvaluator::DEFAULT_ACCEPT_THRESHOLD));
    }

    public function testRejectsAnInvertedThresholdConfiguration(): void
    {
        $this->expectException(\Nizam\Platform\Exception\InvalidArgumentException::class);

        new ConfidenceEvaluator(acceptThreshold: 0.4, moreWorkersThreshold: 0.8);
    }
}
