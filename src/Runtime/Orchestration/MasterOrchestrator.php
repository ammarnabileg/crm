<?php

declare(strict_types=1);

namespace Nizam\Runtime\Orchestration;

use Nizam\Kernel\Domain\Clock;
use Nizam\Kernel\Domain\TenantId;
use Nizam\Kernel\Domain\UserId;
use Nizam\Platform\Plugin\Contract\ManagerPlugin;
use Nizam\Platform\Plugin\Contract\WorkerPlugin;
use Nizam\Platform\Plugin\PermissionSet;
use Nizam\Platform\Support\Assert;
use Nizam\Runtime\Execution\Application\ValueObject\ExecutionRequest;
use Nizam\Runtime\Execution\Application\ValueObject\ExecutionResult;
use Nizam\Runtime\Execution\Domain\ExecutionId;
use Nizam\Runtime\Execution\Domain\ExecutionStep;
use Nizam\Runtime\Execution\Domain\ExecutionStepId;
use Nizam\Runtime\Execution\Domain\ValueObject\RetryPolicy;
use Nizam\Runtime\Execution\Domain\ValueObject\TimeoutPolicy;
use Nizam\Runtime\Execution\Domain\ValueObject\WorkerResult;
use Nizam\Runtime\Orchestration\Exception\OrchestrationException;
use Nizam\Runtime\Orchestration\Port\AutomationSelector;
use Nizam\Runtime\Orchestration\Port\DepartmentResolver;
use Nizam\Runtime\Orchestration\Port\ExecutionEngineFactory;
use Nizam\Runtime\Orchestration\Port\ManagerAgent;
use Nizam\Runtime\Orchestration\Port\ManagerPluginResolver;
use Nizam\Runtime\Orchestration\Port\PermissionProvider;
use Nizam\Runtime\Orchestration\Port\TenantProvider;
use Nizam\Runtime\Orchestration\Port\UserProvider;
use Nizam\Runtime\Orchestration\Port\WorkerPluginResolver;
use Nizam\Runtime\Orchestration\ValueObject\DispatchMode;
use Nizam\Runtime\Orchestration\ValueObject\ExecutionContext;
use Nizam\Runtime\Orchestration\ValueObject\ManagerDecision;
use Nizam\Runtime\Orchestration\ValueObject\OrchestrationRequest;
use Nizam\Runtime\Orchestration\ValueObject\OrchestrationResponse;
use Nizam\Runtime\Orchestration\ValueObject\WorkerAssignment;
use Nizam\Runtime\Orchestration\ValueObject\WorkTask;
use Nizam\Runtime\Orchestration\Service\CoordinatedWorkerDispatcher;
use Nizam\Runtime\Orchestration\Service\ManagerDrivenPlanner;

/**
 * The single, sole entry point into the AI Runtime.
 *
 * Nothing executes work except by passing an {@see OrchestrationRequest} to {@see self::handle()}. In one
 * deterministic pass the orchestrator: validates the request; resolves and authorizes the tenant, the
 * acting user, and the granted permissions (via the injected provider ports); routes the intent to a
 * department and its Manager plugin (via the {@see DepartmentResolver} / {@see ManagerPluginResolver});
 * builds the request-scoped {@see ExecutionContext}; asks the manager (through the {@see ManagerAgent})
 * to plan the work into {@see WorkTask}s; resolves each task's worker plugin and lets the
 * {@see WorkerCoordinator} dispatch them (Sequential, Parallel fan-out, or Collaborative re-entry —
 * workers never talk to each other or to users directly); collects the {@see WorkerResult}s; drives the
 * whole run through the {@see \Nizam\Runtime\Execution\Application\ExecutionEngine} (which folds cost,
 * performance, and the timeline and persists the event-sourced aggregate); merges the results with the
 * {@see ResultMerger}; scores them with the {@see ConfidenceEvaluator}; and finally asks the manager to
 * {@see ManagerAgent::decide()} on the merged, scored output. The manager's {@see ManagerDecision} — never
 * a worker's — is returned in the {@see OrchestrationResponse}. Every step above is recorded on the
 * execution's timeline through the engine's normal event flow, giving one auditable path per request.
 *
 * The orchestrator holds no I/O of its own: persistence, locking, and event publication live behind the
 * {@see ExecutionEngineFactory}; every other collaborator is a port. Given deterministic collaborators
 * the whole `handle()` is deterministic.
 */
final class MasterOrchestrator
{
    /**
     * @param TenantProvider          $tenants        Resolves and authorizes the request's tenant.
     * @param UserProvider            $users          Resolves and authorizes the request's acting user.
     * @param PermissionProvider      $permissions    Resolves the permissions granted for the request.
     * @param DepartmentResolver      $departments    Routes an intent to a department and manager reference.
     * @param ManagerPluginResolver   $managerPlugins Loads the Manager plugin leading the department.
     * @param WorkerPluginResolver    $workerPlugins  Loads the Worker plugin each task is dispatched to.
     * @param ManagerAgent            $managerAgent   Drives the manager's planning and decision.
     * @param WorkerCoordinator       $coordinator    Dispatches workers in the three coordination modes.
     * @param ResultMerger            $merger         Folds worker results into one merged result.
     * @param ConfidenceEvaluator     $evaluator      Scores the merged result for the manager.
     * @param ExecutionEngineFactory  $engineFactory  Builds a request-scoped execution engine.
     * @param AutomationSelector      $automations    Selects automations for worker-declared goals.
     * @param Clock                   $clock          The time source for deadlines and stamps.
     */
    public function __construct(
        private readonly TenantProvider $tenants,
        private readonly UserProvider $users,
        private readonly PermissionProvider $permissions,
        private readonly DepartmentResolver $departments,
        private readonly ManagerPluginResolver $managerPlugins,
        private readonly WorkerPluginResolver $workerPlugins,
        private readonly ManagerAgent $managerAgent,
        private readonly WorkerCoordinator $coordinator,
        private readonly ResultMerger $merger,
        private readonly ConfidenceEvaluator $evaluator,
        private readonly ExecutionEngineFactory $engineFactory,
        private readonly AutomationSelector $automations,
        private readonly Clock $clock,
    ) {
    }

    /**
     * Handle a request end-to-end and return the manager's decision and the execution outcome.
     *
     * @param OrchestrationRequest $request The request to run (the Runtime's sole input).
     *
     * @throws OrchestrationException When the request is unroutable, unauthorized, or a plugin is missing.
     *
     * @return OrchestrationResponse The final state, manager decision, and execution outcome.
     */
    public function handle(OrchestrationRequest $request): OrchestrationResponse
    {
        $this->validate($request);

        $tenantId = $request->tenantId();
        $this->resolveTenant($tenantId);
        $this->resolveUser($tenantId, $request->userId());
        $grantedPermissions = $this->permissions->resolve($tenantId, $request->userId(), $request->intentRef());

        $assignment = $this->departments->resolve($tenantId, $request->intentRef(), $request->departmentRef());
        if ($assignment === null) {
            throw OrchestrationException::departmentUnresolved($request->intentRef());
        }

        $manager = $this->managerPlugins->resolve($tenantId, $assignment->managerRef());
        if ($manager === null) {
            throw OrchestrationException::managerPluginMissing($assignment->managerRef());
        }

        $executionId = ExecutionId::generate();
        $timeoutPolicy = TimeoutPolicy::default();
        $context = $this->buildContext($executionId, $request, $grantedPermissions, $timeoutPolicy);

        $tasks = $this->planTasks($manager, $request, $context);
        $assignments = $this->assign($tenantId, $tasks);

        $results = $this->coordinator->dispatch($assignments, $this->dispatchMode($tasks), $context);

        $steps = $this->buildSteps($tasks);
        $resultsByStepId = $this->mapResults($steps, $results);

        $executionRequest = $this->buildExecutionRequest($executionId, $request, $assignment->departmentRef(), $assignment->managerRef(), $timeoutPolicy);
        $engine = $this->engineFactory->build(
            new ManagerDrivenPlanner($steps),
            new CoordinatedWorkerDispatcher($resultsByStepId),
        );
        $executionResult = $engine->execute($executionRequest);

        $decision = $this->decide($manager, $results, $context, $executionResult);

        return OrchestrationResponse::fromExecution($executionResult, $decision);
    }

    /**
     * Validate the request's shape before any resolution work.
     *
     * @throws OrchestrationException When the request is malformed.
     */
    private function validate(OrchestrationRequest $request): void
    {
        if (trim($request->intentRef()) === '') {
            throw OrchestrationException::invalidRequest('The request must name a non-empty intent.');
        }
    }

    /**
     * Resolve the tenant and assert it is known and active.
     *
     * @throws OrchestrationException When the tenant is unknown or inactive.
     */
    private function resolveTenant(TenantId $tenantId): void
    {
        $tenant = $this->tenants->resolve($tenantId);
        if ($tenant === null || !$tenant->isActive()) {
            throw OrchestrationException::tenantUnavailable($tenantId->toString());
        }
    }

    /**
     * Resolve the acting user (when named) and assert it is known and active for the tenant.
     *
     * @throws OrchestrationException When a named user is unknown or inactive.
     */
    private function resolveUser(TenantId $tenantId, ?UserId $userId): void
    {
        if ($userId === null) {
            return;
        }

        $user = $this->users->resolve($tenantId, $userId);
        if ($user === null || !$user->isActive()) {
            throw OrchestrationException::userUnavailable($userId->toString());
        }
    }

    /**
     * Build the request-scoped execution context, deriving the deadline from the timeout policy.
     */
    private function buildContext(
        ExecutionId $executionId,
        OrchestrationRequest $request,
        PermissionSet $grantedPermissions,
        TimeoutPolicy $timeoutPolicy,
    ): ExecutionContext {
        $now = $this->clock->now();
        $deadline = $now->modify(sprintf('+%d milliseconds', $timeoutPolicy->wallClockMs()));

        return new ExecutionContext(
            executionId: $executionId,
            tenantId: $request->tenantId(),
            userId: $request->userId(),
            grantedPermissions: $grantedPermissions,
            correlationId: $executionId->toString(),
            deadline: $deadline,
        );
    }

    /**
     * Ask the manager to plan the request into tasks, asserting the plan is non-empty.
     *
     * @return list<WorkTask>
     *
     * @throws OrchestrationException When the manager plans no tasks.
     */
    private function planTasks(ManagerPlugin $manager, OrchestrationRequest $request, ExecutionContext $context): array
    {
        $tasks = $this->managerAgent->plan($manager, $request, $context);
        if ($tasks === []) {
            throw OrchestrationException::invalidRequest('The manager planned no work for the request.');
        }

        foreach ($tasks as $task) {
            Assert::that($task instanceof WorkTask, 'A manager plan must contain only WorkTask instances.');
        }

        return array_values($tasks);
    }

    /**
     * Resolve each task's worker plugin, pairing it with the task as a {@see WorkerAssignment}.
     *
     * @param list<WorkTask> $tasks
     *
     * @return list<WorkerAssignment>
     *
     * @throws OrchestrationException When a task's worker plugin is unavailable.
     */
    private function assign(TenantId $tenantId, array $tasks): array
    {
        $assignments = [];
        foreach ($tasks as $task) {
            $worker = $this->workerPlugins->resolve($tenantId, $task->workerRef());
            if ($worker === null) {
                throw OrchestrationException::workerPluginMissing($task->workerRef());
            }

            $assignments[] = new WorkerAssignment($worker, $task);
        }

        return $assignments;
    }

    /**
     * Choose the dispatch mode for a plan: parallel fan-out when independent tasks exist, else sequential.
     *
     * A single task never benefits from fan-out, so it runs sequentially; multiple tasks are dispatched as
     * a deterministic in-process parallel fan-out. Collaborative dispatch is entered explicitly through the
     * coordinator's collaborative API and is not inferred here.
     *
     * @param list<WorkTask> $tasks
     */
    private function dispatchMode(array $tasks): DispatchMode
    {
        return count($tasks) > 1 ? DispatchMode::Parallel : DispatchMode::Sequential;
    }

    /**
     * Turn each planned task into an ordered {@see ExecutionStep} for the engine to drive.
     *
     * @param list<WorkTask> $tasks
     *
     * @return list<ExecutionStep>
     */
    private function buildSteps(array $tasks): array
    {
        $steps = [];
        foreach ($tasks as $task) {
            $steps[] = ExecutionStep::assign(
                ExecutionStepId::generate(),
                sprintf('%s (%s)', $task->intent(), $task->taskId()),
                $task->workerRef(),
            );
        }

        return $steps;
    }

    /**
     * Key the coordinator's results by the step id they belong to, in planned order.
     *
     * @param list<ExecutionStep> $steps
     * @param list<WorkerResult>  $results
     *
     * @return array<string, WorkerResult>
     *
     * @throws OrchestrationException When the result count does not match the step count.
     */
    private function mapResults(array $steps, array $results): array
    {
        if (count($steps) !== count($results)) {
            throw OrchestrationException::invalidRequest(
                'The coordinator produced a different number of results than there were planned steps.',
            );
        }

        $mapped = [];
        foreach ($steps as $index => $step) {
            $mapped[$step->stepId()->toString()] = $results[$index];
        }

        return $mapped;
    }

    /**
     * Build the execution request the engine runs, reusing the orchestrator's execution id and deadline.
     */
    private function buildExecutionRequest(
        ExecutionId $executionId,
        OrchestrationRequest $request,
        string $departmentRef,
        string $managerRef,
        TimeoutPolicy $timeoutPolicy,
    ): ExecutionRequest {
        return new ExecutionRequest(
            executionId: $executionId,
            tenantId: $request->tenantId(),
            userId: $request->userId(),
            intentRef: $request->intentRef(),
            payload: $request->payload(),
            departmentRef: $departmentRef,
            managerRef: $managerRef,
            retryPolicy: RetryPolicy::default(),
            timeoutPolicy: $timeoutPolicy,
            labels: [],
        );
    }

    /**
     * Merge and score the worker results, then ask the manager to decide — unless the run never completed.
     *
     * When the execution did not complete (e.g. a worker error failed it), no decision is manufactured;
     * the response carries the failed execution and a null decision so the caller sees the honest outcome.
     *
     * @param list<WorkerResult> $results
     */
    private function decide(
        ManagerPlugin $manager,
        array $results,
        ExecutionContext $context,
        ExecutionResult $executionResult,
    ): ?ManagerDecision {
        $merged = $this->merger->merge($results);
        $assessment = $this->evaluator->evaluate($merged);

        if (!$executionResult->isCompleted()) {
            return null;
        }

        return $this->managerAgent->decide($manager, $merged, $assessment, $context);
    }

    /**
     * Select an automation reference for a worker-declared goal within a tenant.
     *
     * Exposed on the orchestrator because automation selection is a Runtime seam: a worker declares a goal
     * and the Runtime — never the worker — chooses the automation, keeping selection auditable and
     * tenant-scoped. Returns null when no installed automation satisfies the goal.
     */
    public function selectAutomation(TenantId $tenantId, string $goal): ?string
    {
        return $this->automations->select($tenantId, $goal);
    }
}
