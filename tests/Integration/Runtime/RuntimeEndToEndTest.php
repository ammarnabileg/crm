<?php

declare(strict_types=1);

namespace Nizam\Tests\Integration\Runtime;

use Nizam\Kernel\Domain\TenantId;
use Nizam\Platform\Support\SystemClock;
use Nizam\Runtime\Execution\Application\RetryEngine;
use Nizam\Runtime\Execution\Domain\ExecutionState;
use Nizam\Runtime\Execution\Domain\Port\ExecutionEventPublisher;
use Nizam\Runtime\Infrastructure\Migration\SqliteSchema;
use Nizam\Runtime\Infrastructure\Persistence\Pdo\ExecutionEventSerializer;
use Nizam\Runtime\Infrastructure\Persistence\Pdo\ExecutionProjectionWriter;
use Nizam\Runtime\Infrastructure\Persistence\Pdo\ExecutionRowMapper;
use Nizam\Runtime\Infrastructure\Persistence\Pdo\PdoExecutionEventStore;
use Nizam\Runtime\Infrastructure\Persistence\Pdo\PdoExecutionLockManager;
use Nizam\Runtime\Infrastructure\Persistence\Pdo\PdoExecutionRepository;
use Nizam\Runtime\Infrastructure\Provider\InMemory\InMemoryAutomationSelector;
use Nizam\Runtime\Infrastructure\Provider\InMemory\InMemoryDepartmentResolver;
use Nizam\Runtime\Infrastructure\Provider\InMemory\InMemoryManagerPluginResolver;
use Nizam\Runtime\Infrastructure\Provider\InMemory\InMemoryPermissionProvider;
use Nizam\Runtime\Infrastructure\Provider\InMemory\InMemoryTenantProvider;
use Nizam\Runtime\Infrastructure\Provider\InMemory\InMemoryUserProvider;
use Nizam\Runtime\Infrastructure\Provider\InMemory\InMemoryWorkerPluginResolver;
use Nizam\Runtime\Infrastructure\RuntimeExecutionEngineFactory;
use Nizam\Runtime\Infrastructure\Testing\FakeManagerPlugin;
use Nizam\Runtime\Infrastructure\Testing\FakeWorkerPlugin;
use Nizam\Runtime\Orchestration\ConfidenceEvaluator;
use Nizam\Runtime\Orchestration\MasterOrchestrator;
use Nizam\Runtime\Orchestration\ResultMerger;
use Nizam\Runtime\Orchestration\Testing\DeterministicManagerAgent;
use Nizam\Runtime\Orchestration\Testing\DeterministicWorkerInvoker;
use Nizam\Runtime\Orchestration\ValueObject\DecisionOutcome;
use Nizam\Runtime\Orchestration\ValueObject\OrchestrationRequest;
use Nizam\Runtime\Orchestration\WorkerCoordinator;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MasterOrchestrator::class)]
#[CoversClass(RuntimeExecutionEngineFactory::class)]
#[CoversClass(ExecutionProjectionWriter::class)]
#[CoversClass(FakeManagerPlugin::class)]
#[CoversClass(FakeWorkerPlugin::class)]
final class RuntimeEndToEndTest extends TestCase
{
    private const string INTENT = 'crm.lead.enrich';

    private PDO $connection;
    private PdoExecutionRepository $repository;
    private PdoExecutionEventStore $eventStore;
    private ExecutionProjectionWriter $projections;
    private SystemClock $clock;

    protected function setUp(): void
    {
        $this->connection = new PDO('sqlite::memory:');
        $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        SqliteSchema::apply($this->connection);

        $this->clock = new SystemClock();
        $this->eventStore = new PdoExecutionEventStore($this->connection, new ExecutionEventSerializer());
        $this->repository = new PdoExecutionRepository($this->connection, $this->eventStore, new ExecutionRowMapper());
        $this->projections = new ExecutionProjectionWriter($this->connection);
    }

    public function testHandlePersistsExecutionTimelineDecisionAndWorkerResults(): void
    {
        $tenantId = TenantId::generate();

        $tenants = (new InMemoryTenantProvider())->seed($tenantId, 'Acme');
        $users = new InMemoryUserProvider();
        $permissions = (new InMemoryPermissionProvider())
            ->grantKeys($tenantId, [FakeWorkerPlugin::REQUIRED_PERMISSION]);
        $departments = (new InMemoryDepartmentResolver())
            ->route($tenantId, self::INTENT, 'sales', 'runtime.fake-manager');
        $managers = (new InMemoryManagerPluginResolver())->seed($tenantId, new FakeManagerPlugin());
        $workers = (new InMemoryWorkerPluginResolver())->seed($tenantId, new FakeWorkerPlugin());
        $automations = new InMemoryAutomationSelector();

        $engineFactory = new RuntimeExecutionEngineFactory(
            $this->repository,
            $this->eventStore,
            new PdoExecutionLockManager($this->connection, $this->clock),
            $this->nullPublisher(),
            new RetryEngine(),
            $this->clock,
        );

        $orchestrator = new MasterOrchestrator(
            tenants: $tenants,
            users: $users,
            permissions: $permissions,
            departments: $departments,
            managerPlugins: $managers,
            workerPlugins: $workers,
            managerAgent: new DeterministicManagerAgent('runtime.fake-worker', 2),
            coordinator: new WorkerCoordinator(new DeterministicWorkerInvoker(0.9)),
            merger: new ResultMerger(),
            evaluator: new ConfidenceEvaluator(),
            engineFactory: $engineFactory,
            automations: $automations,
            clock: $this->clock,
        );

        $response = $orchestrator->handle(
            OrchestrationRequest::create($tenantId->toString(), null, self::INTENT, ['lead' => 'acme'], 'sales'),
        );

        self::assertSame(ExecutionState::Completed, $response->finalState());
        self::assertSame(DecisionOutcome::Approved, $response->outcome());

        // Project the read-side rows the Execution Monitor reads from.
        $execution = $this->repository->ofId($response->executionId());
        self::assertNotNull($execution);
        $this->projections->project($execution, $response->managerDecision(), $this->clock->now());

        $executionId = $response->executionId()->toString();

        // The execution snapshot is persisted and live.
        self::assertSame(
            1,
            (int) $this->connection->query(
                "SELECT COUNT(*) FROM executions WHERE id = '$executionId' AND deleted_at IS NULL",
            )->fetchColumn(),
        );

        // The append-only history recorded the full lifecycle.
        self::assertGreaterThanOrEqual(
            1,
            (int) $this->connection->query(
                "SELECT COUNT(*) FROM execution_history
                  WHERE execution_id = '$executionId' AND event_name = 'runtime.execution_completed'",
            )->fetchColumn(),
        );

        // The human-readable timeline was written.
        self::assertGreaterThan(
            0,
            (int) $this->connection->query(
                "SELECT COUNT(*) FROM execution_timeline WHERE execution_id = '$executionId'",
            )->fetchColumn(),
        );

        // One manager decision row, carrying the approved outcome.
        $decisionRow = $this->connection->query(
            "SELECT outcome, manager_ref FROM manager_decisions WHERE execution_id = '$executionId'",
        )->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($decisionRow);
        self::assertSame('approved', $decisionRow['outcome']);
        self::assertSame('runtime.fake-manager', $decisionRow['manager_ref']);

        // Two worker-result rows (the manager planned two tasks).
        self::assertSame(
            2,
            (int) $this->connection->query(
                "SELECT COUNT(*) FROM worker_results WHERE execution_id = '$executionId'",
            )->fetchColumn(),
        );

        // Metrics and evidence rows were projected too.
        self::assertSame(
            1,
            (int) $this->connection->query(
                "SELECT COUNT(*) FROM execution_metrics WHERE execution_id = '$executionId'",
            )->fetchColumn(),
        );
        self::assertGreaterThan(
            0,
            (int) $this->connection->query(
                "SELECT COUNT(*) FROM evidence WHERE execution_id = '$executionId'",
            )->fetchColumn(),
        );

        // Re-projecting is idempotent: still exactly one decision row and two worker-result rows.
        $this->projections->project($execution, $response->managerDecision(), $this->clock->now());
        self::assertSame(
            1,
            (int) $this->connection->query(
                "SELECT COUNT(*) FROM manager_decisions WHERE execution_id = '$executionId'",
            )->fetchColumn(),
        );
        self::assertSame(
            2,
            (int) $this->connection->query(
                "SELECT COUNT(*) FROM worker_results WHERE execution_id = '$executionId'",
            )->fetchColumn(),
        );

        // Tenant isolation on reads.
        self::assertCount(1, $this->repository->ofTenant($tenantId));
        self::assertCount(0, $this->repository->ofTenant(TenantId::generate()));
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
