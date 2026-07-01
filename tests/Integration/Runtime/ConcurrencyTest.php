<?php

declare(strict_types=1);

namespace Nizam\Tests\Integration\Runtime;

use Nizam\Kernel\Domain\Clock;
use Nizam\Kernel\Domain\TenantId;
use Nizam\Platform\Support\SystemClock;
use Nizam\Runtime\Execution\Application\ExecutionEngine;
use Nizam\Runtime\Execution\Application\RetryEngine;
use Nizam\Runtime\Execution\Application\ValueObject\ExecutionRequest;
use Nizam\Runtime\Execution\Domain\ExecutionId;
use Nizam\Runtime\Execution\Domain\ExecutionState;
use Nizam\Runtime\Execution\Domain\ExecutionStep;
use Nizam\Runtime\Execution\Domain\ExecutionStepId;
use Nizam\Runtime\Execution\Domain\Port\ExecutionEventPublisher;
use Nizam\Runtime\Execution\Domain\ValueObject\EvidenceItem;
use Nizam\Runtime\Execution\Domain\ValueObject\RetryPolicy;
use Nizam\Runtime\Execution\Domain\ValueObject\TimeoutPolicy;
use Nizam\Runtime\Execution\Domain\ValueObject\WorkerResult;
use Nizam\Runtime\Infrastructure\Migration\SqliteSchema;
use Nizam\Runtime\Infrastructure\Persistence\Pdo\ExecutionEventSerializer;
use Nizam\Runtime\Infrastructure\Persistence\Pdo\ExecutionRowMapper;
use Nizam\Runtime\Infrastructure\Persistence\Pdo\PdoExecutionEventStore;
use Nizam\Runtime\Infrastructure\Persistence\Pdo\PdoExecutionLockManager;
use Nizam\Runtime\Infrastructure\Persistence\Pdo\PdoExecutionRepository;
use Nizam\Runtime\Infrastructure\RuntimeExecutionEngineFactory;
use Nizam\Runtime\Orchestration\Service\CoordinatedWorkerDispatcher;
use Nizam\Runtime\Orchestration\Service\ManagerDrivenPlanner;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PdoExecutionLockManager::class)]
#[CoversClass(RuntimeExecutionEngineFactory::class)]
final class ConcurrencyTest extends TestCase
{
    private PDO $connection;
    private PdoExecutionRepository $repository;
    private PdoExecutionEventStore $eventStore;
    private PdoExecutionLockManager $locks;
    private Clock $clock;

    protected function setUp(): void
    {
        $this->connection = new PDO('sqlite::memory:');
        $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        SqliteSchema::apply($this->connection);

        $this->clock = new SystemClock();
        $this->eventStore = new PdoExecutionEventStore($this->connection, new ExecutionEventSerializer());
        $this->repository = new PdoExecutionRepository($this->connection, $this->eventStore, new ExecutionRowMapper());
        $this->locks = new PdoExecutionLockManager($this->connection, $this->clock);
    }

    public function testRunningTheSameExecutionTwiceIsIdempotentAndPersistsOnce(): void
    {
        $tenantId = TenantId::generate();
        $executionId = ExecutionId::generate();
        $request = $this->request($tenantId, $executionId);

        $engine = $this->engine();

        $first = $engine->execute($request);
        // A second execute for the same id finds a terminal execution and returns its stored outcome
        // (the idempotency guard) rather than running the pipeline again.
        $second = $engine->execute($request);

        self::assertSame(ExecutionState::Completed, $first->finalState());
        self::assertSame(ExecutionState::Completed, $second->finalState());
        self::assertTrue($first->executionId()->equals($second->executionId()));

        // Exactly one snapshot row and one completed-event exist for the execution.
        $snapshotCount = (int) $this->connection
            ->query("SELECT COUNT(*) FROM executions WHERE id = '" . $executionId->toString() . "'")
            ->fetchColumn();
        self::assertSame(1, $snapshotCount);

        $completedEvents = (int) $this->connection
            ->query(
                "SELECT COUNT(*) FROM execution_history
                  WHERE execution_id = '" . $executionId->toString() . "'
                    AND event_name = 'runtime.execution_completed'",
            )
            ->fetchColumn();
        self::assertSame(1, $completedEvents, 'The pipeline must run once, not twice.');
    }

    public function testAHeldLockSerializesASecondEngineRun(): void
    {
        $tenantId = TenantId::generate();
        $executionId = ExecutionId::generate();
        $request = $this->request($tenantId, $executionId);

        // Take the lock out of band, simulating another node mid-run.
        $held = $this->locks->acquire($executionId->toString(), 30_000);
        self::assertNotNull($held);

        $engine = $this->engine();

        // With the lock held (and no terminal snapshot yet), the engine cannot acquire it and raises.
        $this->expectException(\Nizam\Runtime\Execution\Application\Exception\ExecutionApplicationException::class);
        $engine->execute($request);
    }

    private function engine(): ExecutionEngine
    {
        $factory = new RuntimeExecutionEngineFactory(
            $this->repository,
            $this->eventStore,
            $this->locks,
            $this->nullPublisher(),
            new RetryEngine(),
            $this->clock,
        );

        return $factory->build(
            new ManagerDrivenPlanner($this->steps()),
            new CoordinatedWorkerDispatcher($this->results()),
        );
    }

    /**
     * @return list<ExecutionStep>
     */
    private function steps(): array
    {
        return [ExecutionStep::assign($this->stepId(), 'do work', 'runtime.fake-worker')];
    }

    /**
     * @return array<string, WorkerResult>
     */
    private function results(): array
    {
        return [
            $this->stepId()->toString() => new WorkerResult(
                taskResult: ['ok' => true],
                evidence: [new EvidenceItem('record', 'r1', 'a record', $this->clock->now())],
                reasoningSummary: 'done',
                confidence: 0.95,
                executionTimeMs: 1,
                executionCostMicros: 1,
                resourcesUsed: ['tokens' => 1],
            ),
        ];
    }

    private function stepId(): ExecutionStepId
    {
        static $id = null;
        if ($id === null) {
            $id = ExecutionStepId::generate();
        }

        return $id;
    }

    private function request(TenantId $tenantId, ExecutionId $executionId): ExecutionRequest
    {
        return new ExecutionRequest(
            executionId: $executionId,
            tenantId: $tenantId,
            userId: null,
            intentRef: 'crm.enrich',
            payload: ['lead' => 'acme'],
            departmentRef: 'sales',
            managerRef: 'mgr',
            retryPolicy: RetryPolicy::none(),
            timeoutPolicy: TimeoutPolicy::default(),
            labels: [],
        );
    }

    private function nullPublisher(): ExecutionEventPublisher
    {
        return new class implements ExecutionEventPublisher {
            public function publish(array $events): void
            {
            }
        };
    }
}
