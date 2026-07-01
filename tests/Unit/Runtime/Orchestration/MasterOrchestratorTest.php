<?php

declare(strict_types=1);

namespace Nizam\Tests\Unit\Runtime\Orchestration;

use Nizam\Kernel\Domain\TenantId;
use Nizam\Kernel\Domain\UserId;
use Nizam\Platform\Support\SystemClock;
use Nizam\Runtime\Execution\Domain\ExecutionState;
use Nizam\Runtime\Orchestration\ConfidenceEvaluator;
use Nizam\Runtime\Orchestration\Exception\OrchestrationException;
use Nizam\Runtime\Orchestration\MasterOrchestrator;
use Nizam\Runtime\Orchestration\ResultMerger;
use Nizam\Runtime\Orchestration\Testing\DeterministicManagerAgent;
use Nizam\Runtime\Orchestration\Testing\DeterministicWorkerInvoker;
use Nizam\Runtime\Orchestration\Testing\FakeManagerPlugin;
use Nizam\Runtime\Orchestration\Testing\FakeWorkerPlugin;
use Nizam\Runtime\Orchestration\Testing\InMemoryAutomationSelector;
use Nizam\Runtime\Orchestration\Testing\InMemoryDepartmentResolver;
use Nizam\Runtime\Orchestration\Testing\InMemoryExecutionEngineFactory;
use Nizam\Runtime\Orchestration\Testing\InMemoryManagerPluginResolver;
use Nizam\Runtime\Orchestration\Testing\InMemoryPermissionProvider;
use Nizam\Runtime\Orchestration\Testing\InMemoryTenantProvider;
use Nizam\Runtime\Orchestration\Testing\InMemoryUserProvider;
use Nizam\Runtime\Orchestration\Testing\InMemoryWorkerPluginResolver;
use Nizam\Runtime\Orchestration\ValueObject\DecisionOutcome;
use Nizam\Runtime\Orchestration\ValueObject\OrchestrationRequest;
use Nizam\Runtime\Orchestration\WorkerCoordinator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MasterOrchestrator::class)]
final class MasterOrchestratorTest extends TestCase
{
    private const string TENANT = '00000000-0000-7000-8000-000000000001';
    private const string INTENT = 'crm.lead.enrich';

    private TenantId $tenantId;
    private InMemoryTenantProvider $tenants;
    private InMemoryUserProvider $users;
    private InMemoryPermissionProvider $permissions;
    private InMemoryDepartmentResolver $departments;
    private InMemoryManagerPluginResolver $managers;
    private InMemoryWorkerPluginResolver $workers;
    private InMemoryAutomationSelector $automations;
    private InMemoryExecutionEngineFactory $engineFactory;

    protected function setUp(): void
    {
        $this->tenantId = TenantId::fromString(self::TENANT);
        $this->tenants = (new InMemoryTenantProvider())->seed($this->tenantId);
        $this->users = new InMemoryUserProvider();
        $this->permissions = (new InMemoryPermissionProvider())
            ->grantKeys($this->tenantId, [FakeWorkerPlugin::REQUIRED_PERMISSION]);
        $this->departments = (new InMemoryDepartmentResolver())
            ->route($this->tenantId, self::INTENT, 'sales', 'runtime.fake-manager');
        $this->managers = (new InMemoryManagerPluginResolver())
            ->seed($this->tenantId, new FakeManagerPlugin());
        $this->workers = (new InMemoryWorkerPluginResolver())
            ->seed($this->tenantId, new FakeWorkerPlugin());
        $this->automations = new InMemoryAutomationSelector();
        $this->engineFactory = InMemoryExecutionEngineFactory::create(new SystemClock());
    }

    private function orchestrator(
        DeterministicManagerAgent $agent,
        DeterministicWorkerInvoker $invoker,
    ): MasterOrchestrator {
        return new MasterOrchestrator(
            tenants: $this->tenants,
            users: $this->users,
            permissions: $this->permissions,
            departments: $this->departments,
            managerPlugins: $this->managers,
            workerPlugins: $this->workers,
            managerAgent: $agent,
            coordinator: new WorkerCoordinator($invoker),
            merger: new ResultMerger(),
            evaluator: new ConfidenceEvaluator(),
            engineFactory: $this->engineFactory,
            automations: $this->automations,
            clock: new SystemClock(),
        );
    }

    private function request(?string $userId = null): OrchestrationRequest
    {
        return OrchestrationRequest::create(self::TENANT, $userId, self::INTENT, ['lead' => 'acme'], 'sales');
    }

    public function testHappyPathPlansDispatchesMergesAndApproves(): void
    {
        $orchestrator = $this->orchestrator(
            new DeterministicManagerAgent('runtime.fake-worker', 2),
            new DeterministicWorkerInvoker(0.9),
        );

        $response = $orchestrator->handle($this->request());

        self::assertSame(ExecutionState::Completed, $response->finalState());
        self::assertTrue($response->isCompleted());
        self::assertSame(DecisionOutcome::Approved, $response->outcome());
        self::assertNotNull($response->managerDecision());
        self::assertSame(2, $response->managerDecision()->mergedResult()->resultCount());
        self::assertNotEmpty($response->timeline()->entries());
        self::assertNotEmpty($this->engineFactory->store()->publishedEvents());
    }

    public function testLowConfidenceLeadsManagerToRequestMoreWorkers(): void
    {
        $invoker = (new DeterministicWorkerInvoker(0.9))->withConfidence('task-1', 0.2);

        $orchestrator = $this->orchestrator(
            new DeterministicManagerAgent('runtime.fake-worker', 1),
            $invoker,
        );

        $response = $orchestrator->handle($this->request());

        self::assertSame(ExecutionState::Completed, $response->finalState());
        self::assertSame(DecisionOutcome::MoreWorkersRequested, $response->outcome());
    }

    public function testWorkerErrorFailsExecutionAndYieldsNoManufacturedDecision(): void
    {
        $invoker = (new DeterministicWorkerInvoker(0.9))->withErrors('task-1', ['worker exploded']);

        $orchestrator = $this->orchestrator(
            new DeterministicManagerAgent('runtime.fake-worker', 1),
            $invoker,
        );

        $response = $orchestrator->handle($this->request());

        self::assertSame(ExecutionState::Failed, $response->finalState());
        self::assertFalse($response->isCompleted());
        self::assertNull($response->managerDecision());
    }

    public function testResolvesAndAuthorisesAKnownActiveUser(): void
    {
        $userId = UserId::generate();
        $this->users->seed($this->tenantId, $userId);

        $orchestrator = $this->orchestrator(
            new DeterministicManagerAgent('runtime.fake-worker', 1),
            new DeterministicWorkerInvoker(0.9),
        );

        $response = $orchestrator->handle($this->request($userId->toString()));

        self::assertSame(ExecutionState::Completed, $response->finalState());
    }

    public function testRejectsAnInactiveTenantBeforeExecuting(): void
    {
        $this->tenants->seed($this->tenantId, 'Suspended', active: false);

        $orchestrator = $this->orchestrator(
            new DeterministicManagerAgent(),
            new DeterministicWorkerInvoker(),
        );

        $this->expectException(OrchestrationException::class);
        $this->expectExceptionMessageMatches('/TENANT_UNAVAILABLE|not active/');

        $orchestrator->handle($this->request());
    }

    public function testRejectsAnUnknownUser(): void
    {
        $orchestrator = $this->orchestrator(
            new DeterministicManagerAgent(),
            new DeterministicWorkerInvoker(),
        );

        $this->expectException(OrchestrationException::class);

        $orchestrator->handle($this->request(UserId::generate()->toString()));
    }

    public function testRejectsAnUnroutableIntent(): void
    {
        $orchestrator = $this->orchestrator(
            new DeterministicManagerAgent(),
            new DeterministicWorkerInvoker(),
        );

        $this->expectException(OrchestrationException::class);

        $orchestrator->handle(
            OrchestrationRequest::create(self::TENANT, null, 'unknown.intent', []),
        );
    }

    public function testRejectsWhenTheManagerPluginIsMissing(): void
    {
        $this->departments->route($this->tenantId, self::INTENT, 'sales', 'ghost.manager');

        $orchestrator = $this->orchestrator(
            new DeterministicManagerAgent(),
            new DeterministicWorkerInvoker(),
        );

        $this->expectException(OrchestrationException::class);

        $orchestrator->handle($this->request());
    }

    public function testRejectsWhenAWorkerPluginIsMissing(): void
    {
        $orchestrator = $this->orchestrator(
            new DeterministicManagerAgent('nonexistent.worker', 1),
            new DeterministicWorkerInvoker(),
        );

        $this->expectException(OrchestrationException::class);

        $orchestrator->handle($this->request());
    }

    public function testDeniesDispatchWhenPermissionsAreNotGranted(): void
    {
        // A fresh permission provider that grants nothing.
        $this->permissions = new InMemoryPermissionProvider();

        $orchestrator = $this->orchestrator(
            new DeterministicManagerAgent('runtime.fake-worker', 1),
            new DeterministicWorkerInvoker(0.9),
        );

        $this->expectException(OrchestrationException::class);

        $orchestrator->handle($this->request());
    }

    public function testAutomationSelectionIsExposedAsARuntimeSeam(): void
    {
        $this->automations->register($this->tenantId, 'send.email', 'automation.mailer');

        $orchestrator = $this->orchestrator(
            new DeterministicManagerAgent(),
            new DeterministicWorkerInvoker(),
        );

        self::assertSame('automation.mailer', $orchestrator->selectAutomation($this->tenantId, 'send.email'));
        self::assertNull($orchestrator->selectAutomation($this->tenantId, 'unmapped.goal'));
    }
}
