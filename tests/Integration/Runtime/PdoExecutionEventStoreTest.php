<?php

declare(strict_types=1);

namespace Nizam\Tests\Integration\Runtime;

use DateTimeImmutable;
use Nizam\Kernel\Domain\Clock;
use Nizam\Kernel\Domain\TenantId;
use Nizam\Runtime\Execution\Domain\Execution;
use Nizam\Runtime\Execution\Domain\ExecutionId;
use Nizam\Runtime\Execution\Domain\ExecutionStep;
use Nizam\Runtime\Execution\Domain\ExecutionStepId;
use Nizam\Runtime\Execution\Domain\ValueObject\EvidenceItem;
use Nizam\Runtime\Execution\Domain\ValueObject\ExecutionMetadata;
use Nizam\Runtime\Execution\Domain\ValueObject\RetryPolicy;
use Nizam\Runtime\Execution\Domain\ValueObject\TimeoutPolicy;
use Nizam\Runtime\Execution\Domain\ValueObject\WorkerResult;
use Nizam\Runtime\Infrastructure\Migration\SqliteSchema;
use Nizam\Runtime\Infrastructure\Persistence\Pdo\ExecutionEventSerializer;
use Nizam\Runtime\Infrastructure\Persistence\Pdo\PdoExecutionEventStore;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PdoExecutionEventStore::class)]
#[CoversClass(ExecutionEventSerializer::class)]
#[CoversClass(SqliteSchema::class)]
final class PdoExecutionEventStoreTest extends TestCase
{
    private PDO $connection;
    private PdoExecutionEventStore $store;
    private Clock $clock;

    protected function setUp(): void
    {
        $this->connection = new PDO('sqlite::memory:');
        $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        SqliteSchema::apply($this->connection);

        $this->store = new PdoExecutionEventStore($this->connection, new ExecutionEventSerializer());
        $this->clock = new class implements Clock {
            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable('2026-07-01T12:00:00+00:00');
            }
        };
    }

    public function testAppendsAndStreamsEventsInOrder(): void
    {
        $tenantId = TenantId::generate();
        $executionId = ExecutionId::generate();
        $execution = $this->buildCompletedExecution($tenantId, $executionId);

        $recorded = $execution->pullDomainEvents();
        $this->store->append($executionId, $recorded);

        $streamed = $this->store->stream($executionId);

        self::assertCount(count($recorded), $streamed);
        foreach ($recorded as $index => $original) {
            self::assertSame($original->eventName(), $streamed[$index]->eventName());
            self::assertSame($original->aggregateId(), $streamed[$index]->aggregateId());
        }
    }

    public function testStreamedEventsReplayIntoAnIdenticalAggregate(): void
    {
        $tenantId = TenantId::generate();
        $executionId = ExecutionId::generate();
        $execution = $this->buildCompletedExecution($tenantId, $executionId);

        $this->store->append($executionId, $execution->pullDomainEvents());

        $replayed = Execution::replay($this->store->stream($executionId));

        self::assertSame($execution->state(), $replayed->state());
        self::assertTrue($execution->cost()->equals($replayed->cost()));
        self::assertTrue($execution->performance()->equals($replayed->performance()));
        self::assertTrue($execution->timeline()->equals($replayed->timeline()));
        self::assertCount(count($execution->steps()), $replayed->steps());
    }

    public function testAppendIsAdditiveAcrossMultipleCalls(): void
    {
        $tenantId = TenantId::generate();
        $executionId = ExecutionId::generate();

        $execution = Execution::start(
            $executionId,
            new ExecutionMetadata($tenantId, null, null, null, 'crm.enrich'),
            RetryPolicy::default(),
            TimeoutPolicy::default(),
            $this->clock,
        );
        $this->store->append($executionId, $execution->pullDomainEvents());

        $execution->plan($this->clock);
        $this->store->append($executionId, $execution->pullDomainEvents());

        self::assertCount(2, $this->store->stream($executionId));
    }

    public function testStreamsAreIsolatedPerExecution(): void
    {
        $tenantId = TenantId::generate();
        $first = ExecutionId::generate();
        $second = ExecutionId::generate();

        $this->store->append($first, $this->buildCompletedExecution($tenantId, $first)->pullDomainEvents());
        $this->store->append($second, $this->buildCompletedExecution($tenantId, $second)->pullDomainEvents());

        self::assertNotEmpty($this->store->stream($first));
        self::assertNotEmpty($this->store->stream($second));
        // Each stream only carries its own execution's events.
        foreach ($this->store->stream($first) as $event) {
            self::assertSame($first->toString(), $event->aggregateId());
        }
    }

    public function testAppendingAnEmptyBatchIsANoOp(): void
    {
        $executionId = ExecutionId::generate();
        $this->store->append($executionId, []);

        self::assertSame([], $this->store->stream($executionId));
    }

    private function buildCompletedExecution(TenantId $tenantId, ExecutionId $executionId): Execution
    {
        $metadata = new ExecutionMetadata($tenantId, null, 'sales', 'mgr', 'crm.enrich', ['k' => 'v']);
        $execution = Execution::start($executionId, $metadata, RetryPolicy::default(), TimeoutPolicy::default(), $this->clock);
        $execution->plan($this->clock);

        $stepId = ExecutionStepId::generate();
        $execution->assign([ExecutionStep::assign($stepId, 'do work', 'runtime.fake-worker')], $this->clock);
        $execution->run($this->clock);

        $result = new WorkerResult(
            taskResult: ['ok' => true],
            evidence: [new EvidenceItem('record', 'r1', 'a record', $this->clock->now())],
            reasoningSummary: 'did it',
            confidence: 0.95,
            executionTimeMs: 12,
            executionCostMicros: 340,
            resourcesUsed: ['tokens' => 7],
            automationSelected: 'automation.x',
            toolsUsed: ['tool1'],
            warnings: [],
            errors: [],
            recommendations: ['do more'],
            logs: ['log line'],
        );
        $execution->completeStep($stepId, $result, $this->clock);
        $execution->toReview($this->clock);
        $execution->approve('supervisor', $this->clock);
        $execution->complete($this->clock);

        return $execution;
    }
}
