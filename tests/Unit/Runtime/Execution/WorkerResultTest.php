<?php

declare(strict_types=1);

namespace Nizam\Tests\Unit\Runtime\Execution;

use DateTimeImmutable;
use Nizam\Platform\Exception\InvalidArgumentException;
use Nizam\Runtime\Execution\Domain\ValueObject\EvidenceItem;
use Nizam\Runtime\Execution\Domain\ValueObject\WorkerResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the {@see WorkerResult} contract: it carries every spec-mandated field, self-validates its
 * confidence bound and non-negative time/cost, and compares and serializes by value.
 */
#[CoversClass(WorkerResult::class)]
final class WorkerResultTest extends TestCase
{
    private function evidence(string $ref = 'urn:record:1'): EvidenceItem
    {
        return new EvidenceItem('record', $ref, 'a record', new DateTimeImmutable('2026-01-01T00:00:00+00:00'));
    }

    public function testExposesEveryMandatedField(): void
    {
        $evidence = [$this->evidence()];
        $result = new WorkerResult(
            taskResult: ['answer' => 42],
            evidence: $evidence,
            reasoningSummary: 'because',
            confidence: 0.75,
            executionTimeMs: 120,
            executionCostMicros: 500,
            resourcesUsed: ['tokens' => 900],
            automationSelected: 'automation.mailer',
            toolsUsed: ['crm.lookup'],
            warnings: ['low signal'],
            errors: [],
            recommendations: ['double-check the email'],
            logs: ['started', 'finished'],
        );

        self::assertSame(['answer' => 42], $result->taskResult());
        self::assertCount(1, $result->evidence());
        self::assertTrue($result->evidence()[0]->equals($evidence[0]));
        self::assertSame('because', $result->reasoningSummary());
        self::assertSame(0.75, $result->confidence());
        self::assertSame(120, $result->executionTimeMs());
        self::assertSame(500, $result->executionCostMicros());
        self::assertSame(['tokens' => 900], $result->resourcesUsed());
        self::assertSame('automation.mailer', $result->automationSelected());
        self::assertSame(['crm.lookup'], $result->toolsUsed());
        self::assertSame(['low signal'], $result->warnings());
        self::assertSame([], $result->errors());
        self::assertFalse($result->hasErrors());
        self::assertSame(['double-check the email'], $result->recommendations());
        self::assertSame(['started', 'finished'], $result->logs());
    }

    public function testHasErrorsReflectsReportedErrors(): void
    {
        $result = new WorkerResult(
            taskResult: [],
            evidence: [],
            reasoningSummary: 'failed',
            confidence: 0.1,
            executionTimeMs: 0,
            executionCostMicros: 0,
            errors: ['dispatch failed'],
        );

        self::assertTrue($result->hasErrors());
        self::assertSame(['dispatch failed'], $result->errors());
    }

    /**
     * @return iterable<string, array{float}>
     */
    public static function inBoundsConfidence(): iterable
    {
        yield 'floor' => [0.0];
        yield 'mid' => [0.5];
        yield 'ceiling' => [1.0];
    }

    #[DataProvider('inBoundsConfidence')]
    public function testConfidenceWithinBoundsIsAccepted(float $confidence): void
    {
        $result = new WorkerResult(
            taskResult: [],
            evidence: [],
            reasoningSummary: 'ok',
            confidence: $confidence,
            executionTimeMs: 0,
            executionCostMicros: 0,
        );

        self::assertSame($confidence, $result->confidence());
    }

    /**
     * @return iterable<string, array{float}>
     */
    public static function outOfBoundsConfidence(): iterable
    {
        yield 'below zero' => [-0.01];
        yield 'above one' => [1.01];
    }

    #[DataProvider('outOfBoundsConfidence')]
    public function testConfidenceOutOfBoundsIsRejected(float $confidence): void
    {
        $this->expectException(InvalidArgumentException::class);

        new WorkerResult(
            taskResult: [],
            evidence: [],
            reasoningSummary: 'bad',
            confidence: $confidence,
            executionTimeMs: 0,
            executionCostMicros: 0,
        );
    }

    public function testNegativeTimeIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new WorkerResult(
            taskResult: [],
            evidence: [],
            reasoningSummary: 'bad',
            confidence: 0.5,
            executionTimeMs: -1,
            executionCostMicros: 0,
        );
    }

    public function testNegativeCostIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new WorkerResult(
            taskResult: [],
            evidence: [],
            reasoningSummary: 'bad',
            confidence: 0.5,
            executionTimeMs: 0,
            executionCostMicros: -1,
        );
    }

    public function testEvidenceMustContainOnlyEvidenceItems(): void
    {
        $this->expectException(InvalidArgumentException::class);

        /** @phpstan-ignore-next-line intentionally invalid to prove the guard fires. */
        new WorkerResult(
            taskResult: [],
            evidence: ['not-evidence'],
            reasoningSummary: 'bad',
            confidence: 0.5,
            executionTimeMs: 0,
            executionCostMicros: 0,
        );
    }

    public function testEqualityIsStructural(): void
    {
        $a = new WorkerResult(['x' => 1], [$this->evidence()], 'r', 0.5, 1, 2);
        $b = new WorkerResult(['x' => 1], [$this->evidence()], 'r', 0.5, 1, 2);
        $c = new WorkerResult(['x' => 2], [$this->evidence()], 'r', 0.5, 1, 2);

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
    }

    public function testToArrayIsScalarOnlyAndRoundTripsEvidence(): void
    {
        $result = new WorkerResult(
            taskResult: ['ok' => true],
            evidence: [$this->evidence('urn:record:9')],
            reasoningSummary: 'r',
            confidence: 0.5,
            executionTimeMs: 10,
            executionCostMicros: 20,
            automationSelected: null,
        );

        $array = $result->toArray();

        self::assertSame(['ok' => true], $array['taskResult']);
        self::assertSame(0.5, $array['confidence']);
        self::assertNull($array['automationSelected']);
        self::assertCount(1, $array['evidence']);
        self::assertSame('urn:record:9', $array['evidence'][0]['reference']);
    }
}
