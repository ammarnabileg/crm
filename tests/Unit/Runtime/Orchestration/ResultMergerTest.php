<?php

declare(strict_types=1);

namespace Nizam\Tests\Unit\Runtime\Orchestration;

use DateTimeImmutable;
use Nizam\Runtime\Execution\Domain\ValueObject\EvidenceItem;
use Nizam\Runtime\Execution\Domain\ValueObject\WorkerResult;
use Nizam\Runtime\Orchestration\ResultMerger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ResultMerger::class)]
final class ResultMergerTest extends TestCase
{
    private function evidence(string $ref): EvidenceItem
    {
        return new EvidenceItem('record', $ref, 'summary of ' . $ref, new DateTimeImmutable('2020-01-01T00:00:00+00:00'));
    }

    private function workerResult(
        float $confidence,
        int $timeMs,
        int $costMicros,
        array $evidence = [],
        array $recommendations = [],
        array $warnings = [],
        array $errors = [],
    ): WorkerResult {
        return new WorkerResult(
            taskResult: ['ok' => true],
            evidence: $evidence,
            reasoningSummary: 'reasoning',
            confidence: $confidence,
            executionTimeMs: $timeMs,
            executionCostMicros: $costMicros,
            resourcesUsed: [],
            automationSelected: null,
            toolsUsed: [],
            warnings: $warnings,
            errors: $errors,
            recommendations: $recommendations,
            logs: [],
        );
    }

    public function testMergesConfidenceAsMeanAndSumsCostAndTime(): void
    {
        $merger = new ResultMerger();

        $merged = $merger->merge([
            $this->workerResult(0.8, 10, 5),
            $this->workerResult(0.6, 20, 7),
        ]);

        self::assertSame(0.7, $merged->aggregateConfidence());
        self::assertSame(30, $merged->totalTimeMs());
        self::assertSame(12, $merged->totalCostMicros());
        self::assertSame(2, $merged->resultCount());
    }

    public function testDeduplicatesEvidenceByKindAndReference(): void
    {
        $merger = new ResultMerger();
        $shared = $this->evidence('urn:same');

        $merged = $merger->merge([
            $this->workerResult(1.0, 0, 0, [$shared, $this->evidence('urn:a')]),
            $this->workerResult(1.0, 0, 0, [$shared, $this->evidence('urn:b')]),
        ]);

        self::assertCount(3, $merged->evidence());
    }

    public function testCombinesAndDeduplicatesStringLists(): void
    {
        $merger = new ResultMerger();

        $merged = $merger->merge([
            $this->workerResult(1.0, 0, 0, [], ['rec1'], ['warn1'], []),
            $this->workerResult(1.0, 0, 0, [], ['rec1', 'rec2'], ['warn2'], ['err1']),
        ]);

        self::assertSame(['rec1', 'rec2'], $merged->recommendations());
        self::assertSame(['warn1', 'warn2'], $merged->warnings());
        self::assertSame(['err1'], $merged->errors());
        self::assertTrue($merged->hasErrors());
    }

    public function testMergingNothingYieldsZeroConfidenceNotDivideByZero(): void
    {
        $merger = new ResultMerger();

        $merged = $merger->merge([]);

        self::assertSame(0.0, $merged->aggregateConfidence());
        self::assertSame(0, $merged->resultCount());
        self::assertFalse($merged->hasErrors());
    }
}
