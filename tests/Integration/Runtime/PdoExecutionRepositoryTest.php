<?php

declare(strict_types=1);

namespace Nizam\Tests\Integration\Runtime;

use DateTimeImmutable;
use Nizam\Kernel\Domain\Clock;
use Nizam\Kernel\Domain\TenantId;
use Nizam\Kernel\Domain\UserId;
use Nizam\Runtime\Execution\Domain\Execution;
use Nizam\Runtime\Execution\Domain\ExecutionId;
use Nizam\Runtime\Execution\Domain\ExecutionState;
use Nizam\Runtime\Execution\Domain\ExecutionStep;
use Nizam\Runtime\Execution\Domain\ExecutionStepId;
use Nizam\Runtime\Execution\Domain\ValueObject\EvidenceItem;
use Nizam\Runtime\Execution\Domain\ValueObject\ExecutionMetadata;
use Nizam\Runtime\Execution\Domain\ValueObject\RetryPolicy;
use Nizam\Runtime\Execution\Domain\ValueObject\TimeoutPolicy;
use Nizam\Runtime\Execution\Domain\ValueObject\WorkerResult;
use Nizam\Runtime\Infrastructure\Migration\SqliteSchema;
use Nizam\Runtime\Infrastructure\Persistence\Pdo\ExecutionEventSerializer;
use Nizam\Runtime\Infrastructure\Persistence\Pdo\ExecutionRowMapper;
use Nizam\Runtime\Infrastructure\Persistence\Pdo\PdoExecutionEventStore;
use Nizam\Runtime\Infrastructure\Persistence\Pdo\PdoExecutionRepository;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PdoExecutionRepository::class)]
#[CoversClass(ExecutionRowMapper::class)]
final class PdoExecutionRepositoryTest extends TestCase
{
    private PDO $connection;
    private PdoExecutionEventStore $eventStore;
    private PdoExecutionRepository $repository;
    private Clock $clock;

    protected function setUp(): void
    {
        $this->connection = new PDO('sqlite::memory:');
        $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        SqliteSchema::apply($this->connection);

        $this->eventStore = new PdoExecutionEventStore($this->connection, new ExecutionEventSerializer());
        $this->repository = new PdoExecutionRepository($this->connection, $this->eventStore, new ExecutionRowMapper());
        $this->clock = new class implements Clock {
            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable('2026-07-01T12:00:00+00:00');
            }
        };
    }

    public function testSavesAndRehydratesAnExecutionFromItsEventStream(): void
    {
        $tenantId = TenantId::generate();
        $executionId = ExecutionId::generate();
        $execution = $this->buildCompletedExecution($tenantId, $executionId, UserId::generate());

        $this->persist($executionId, $execution);

        $loaded = $this->repository->ofId($executionId);

        self::assertNotNull($loaded);
        self::assertSame(ExecutionState::Completed, $loaded->state());
        self::assertTrue($loaded->executionId()->equals($executionId));
        self::assertTrue($loaded->cost()->equals($execution->cost()));
        self::assertTrue($loaded->performance()->equals($execution->performance()));
        self::assertTrue($loaded->timeline()->equals($execution->timeline()));
        self::assertCount(1, $loaded->steps());
        $result = $loaded->steps()[0]->result();
        self::assertNotNull($result);
        self::assertSame(0.95, $result->confidence());
    }

    public function testSaveUpsertsRatherThanDuplicating(): void
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
        $this->persist($executionId, $execution);

        $execution->plan($this->clock);
        $this->persist($executionId, $execution);

        $count = $this->connection
            ->query("SELECT COUNT(*) FROM executions WHERE id = '" . $executionId->toString() . "'")
            ->fetchColumn();
        self::assertSame(1, (int) $count);

        $loaded = $this->repository->ofId($executionId);
        self::assertNotNull($loaded);
        self::assertSame(ExecutionState::Planning, $loaded->state());
    }

    public function testOfTenantReturnsOnlyTheTenantsLiveExecutions(): void
    {
        $tenantA = TenantId::generate();
        $tenantB = TenantId::generate();

        $a1 = ExecutionId::generate();
        $a2 = ExecutionId::generate();
        $b1 = ExecutionId::generate();

        $this->persist($a1, $this->buildCompletedExecution($tenantA, $a1, null));
        $this->persist($a2, $this->buildCompletedExecution($tenantA, $a2, null));
        $this->persist($b1, $this->buildCompletedExecution($tenantB, $b1, null));

        self::assertCount(2, $this->repository->ofTenant($tenantA));
        self::assertCount(1, $this->repository->ofTenant($tenantB));
        self::assertCount(0, $this->repository->ofTenant(TenantId::generate()));
    }

    public function testUnknownExecutionResolvesToNull(): void
    {
        self::assertNull($this->repository->ofId(ExecutionId::generate()));
    }

    public function testSoftDeletedExecutionIsHiddenFromReads(): void
    {
        $tenantId = TenantId::generate();
        $executionId = ExecutionId::generate();
        $this->persist($executionId, $this->buildCompletedExecution($tenantId, $executionId, null));

        self::assertNotNull($this->repository->ofId($executionId));

        $this->repository->softDelete($tenantId, $executionId, $this->clock->now());

        self::assertNull($this->repository->ofId($executionId));
        self::assertCount(0, $this->repository->ofTenant($tenantId));
    }

    private function persist(ExecutionId $executionId, Execution $execution): void
    {
        $this->eventStore->append($executionId, $execution->pullDomainEvents());
        $this->repository->save($execution);
    }

    private function buildCompletedExecution(TenantId $tenantId, ExecutionId $executionId, ?UserId $userId): Execution
    {
        $metadata = new ExecutionMetadata($tenantId, $userId, 'sales', 'mgr', 'crm.enrich', ['k' => 'v']);
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
        );
        $execution->completeStep($stepId, $result, $this->clock);
        $execution->toReview($this->clock);
        $execution->approve('supervisor', $this->clock);
        $execution->complete($this->clock);

        return $execution;
    }
}
