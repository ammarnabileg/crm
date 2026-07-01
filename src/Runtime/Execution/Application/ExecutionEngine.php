<?php

declare(strict_types=1);

namespace Nizam\Runtime\Execution\Application;

use Nizam\Kernel\Domain\Clock;
use Nizam\Runtime\Execution\Application\Exception\ExecutionApplicationException;
use Nizam\Runtime\Execution\Application\Port\ExecutionPlanner;
use Nizam\Runtime\Execution\Application\Port\WorkerDispatcher;
use Nizam\Runtime\Execution\Application\Stage\AssignStage;
use Nizam\Runtime\Execution\Application\Stage\FinalizeStage;
use Nizam\Runtime\Execution\Application\Stage\PlanStage;
use Nizam\Runtime\Execution\Application\Stage\ReviewStage;
use Nizam\Runtime\Execution\Application\Stage\RunStage;
use Nizam\Runtime\Execution\Application\ValueObject\ExecutionRequest;
use Nizam\Runtime\Execution\Application\ValueObject\ExecutionResult;
use Nizam\Runtime\Execution\Domain\Execution;
use Nizam\Runtime\Execution\Domain\ExecutionState;
use Nizam\Runtime\Execution\Domain\Port\ExecutionEventPublisher;
use Nizam\Runtime\Execution\Domain\Port\ExecutionEventStore;
use Nizam\Runtime\Execution\Domain\Port\ExecutionLockManager;
use Nizam\Runtime\Execution\Domain\Port\ExecutionRepository;
use Nizam\Runtime\Execution\Domain\ValueObject\ExecutionLock;

/**
 * The facade that executes one {@see ExecutionRequest} end-to-end, safely and idempotently.
 *
 * This is the single seam the Master Orchestrator calls to run work. For a request it: guards
 * idempotency by {@see \Nizam\Runtime\Execution\Domain\ExecutionId} (a request whose execution already
 * reached a terminal state is not re-run — its stored outcome is returned); acquires a mutual-exclusion
 * lock through the {@see ExecutionLockManager} so two concurrent runs of the same execution cannot
 * interleave; starts the {@see Execution} aggregate; drives it through the {@see ExecutionPipeline}
 * under a {@see TimeoutManager} anchored to the run's start; and, when an attempt fails, consults the
 * {@see RetryEngine} to either recover-and-resume the execution or leave it failed. Every attempt's
 * recorded events are appended to the {@see ExecutionEventStore}, the refreshed snapshot is saved
 * through the {@see ExecutionRepository}, and the events are published via the
 * {@see ExecutionEventPublisher} — always after the work commits, and always with the lock released in
 * a finally block. The engine performs no worker logic itself; planning and dispatch flow through the
 * injected {@see ExecutionPlanner} and {@see WorkerDispatcher} ports. The result is deterministic given
 * deterministic collaborators.
 */
final class ExecutionEngine
{
    /**
     * The lock time-to-live, in milliseconds, applied while an execution is being processed.
     */
    public const int LOCK_TTL_MS = 30_000;

    /**
     * @param ExecutionRepository     $repository The snapshot store executions are saved to and read from.
     * @param ExecutionEventStore     $eventStore The append-only event stream each attempt is recorded in.
     * @param ExecutionLockManager    $locks      The mutual-exclusion port serializing work per execution.
     * @param ExecutionEventPublisher $publisher  The port that announces recorded events after commit.
     * @param ExecutionPipeline       $pipeline   The stage pipeline that drives an execution's happy path.
     * @param RetryEngine             $retryEngine The service that decides retry-vs-fail on an attempt failure.
     * @param ExecutionPlanner        $planner    The port supplying the steps to assign.
     * @param WorkerDispatcher        $dispatcher The port running each step to a result.
     * @param Clock                   $clock      The time source every mutation and timeout uses.
     */
    public function __construct(
        private readonly ExecutionRepository $repository,
        private readonly ExecutionEventStore $eventStore,
        private readonly ExecutionLockManager $locks,
        private readonly ExecutionEventPublisher $publisher,
        private readonly ExecutionPipeline $pipeline,
        private readonly RetryEngine $retryEngine,
        private readonly ExecutionPlanner $planner,
        private readonly WorkerDispatcher $dispatcher,
        private readonly Clock $clock,
    ) {
    }

    /**
     * Execute a request and return its result, honoring idempotency, locking, retries, and timeouts.
     *
     * @param ExecutionRequest $request The validated request to run.
     *
     * @throws ExecutionApplicationException When the execution's lock cannot be acquired.
     *
     * @return ExecutionResult The final state, metrics, and timeline of the run.
     */
    public function execute(ExecutionRequest $request): ExecutionResult
    {
        $executionId = $request->executionId();

        $existing = $this->repository->ofId($executionId);
        if ($existing !== null && $existing->state()->isTerminal()) {
            return ExecutionResult::fromExecution($existing);
        }

        $lock = $this->locks->acquire($executionId->toString(), self::LOCK_TTL_MS);
        if ($lock === null) {
            throw ExecutionApplicationException::lockUnavailable($executionId->toString());
        }

        try {
            // Re-check idempotency inside the lock: another holder may have finished while we waited.
            $existing = $this->repository->ofId($executionId);
            if ($existing !== null && $existing->state()->isTerminal()) {
                return ExecutionResult::fromExecution($existing);
            }

            $execution = Execution::start(
                $executionId,
                $request->toMetadata(),
                $request->retryPolicy(),
                $request->timeoutPolicy(),
                $this->clock,
            );

            $this->drive($execution, $request);

            return ExecutionResult::fromExecution($execution);
        } finally {
            $this->release($lock);
        }
    }

    /**
     * Drive an execution to a terminal state, retrying via recover-and-resume while the policy permits.
     *
     * The first attempt runs the full pipeline. If it ends {@see ExecutionState::Failed} and the
     * {@see RetryEngine} permits another attempt, the execution is recovered
     * ({@see ExecutionState::Failed} → {@see ExecutionState::Recovered}) and the resume pipeline
     * (Plan→Assign→Run→Review→Finalize) is run again; the aggregate's own retry budget and the retry
     * engine agree on the cap. Every attempt is committed (events appended, snapshot saved, events
     * published) before the next begins, so partial progress is never lost.
     */
    private function drive(Execution $execution, ExecutionRequest $request): void
    {
        $timeout = TimeoutManager::start($request->timeoutPolicy(), $this->clock);

        $context = new PipelineContext(
            $execution,
            $request,
            $this->clock,
            $timeout,
            $this->planner,
            $this->dispatcher,
        );

        $this->pipeline->run($context);
        $this->commit($execution);

        $resumePipeline = new ExecutionPipeline($this->resumeStages());

        // Attempts already made: the initial pipeline run is attempt one. The recover-and-resume path
        // does not flow through the aggregate's own retry() mutator, so the engine counts attempts here
        // and consults the retry engine with that count, guaranteeing the loop is bounded by the policy.
        $attemptsMade = 1;
        while ($execution->state() === ExecutionState::Failed) {
            $decision = $this->retryEngine->decide($request->retryPolicy(), $attemptsMade);
            if (!$decision->shouldRetry()) {
                return;
            }

            ++$attemptsMade;
            $execution->recover($this->clock);
            $resumePipeline->run($context);
            $this->commit($execution);
        }
    }

    /**
     * The stages a resumed (recovered) attempt runs — everything after validation.
     *
     * @return list<\Nizam\Runtime\Execution\Application\Stage\PipelineStage>
     */
    private function resumeStages(): array
    {
        return [
            new PlanStage(),
            new AssignStage(),
            new RunStage(),
            new ReviewStage(),
            new FinalizeStage(),
        ];
    }

    /**
     * Commit an execution attempt: append its new events, save the snapshot, then publish the events.
     *
     * Pulls the events the aggregate recorded during the attempt (clearing its buffer), appends them to
     * the append-only store, persists the refreshed snapshot, and finally publishes the events so
     * listeners react only after the work is durable.
     */
    private function commit(Execution $execution): void
    {
        $events = $execution->pullDomainEvents();
        $this->eventStore->append($execution->executionId(), $events);
        $this->repository->save($execution);
        $this->publisher->publish($events);
    }

    /**
     * Release a held lock, swallowing a release failure so it never masks the run's outcome.
     */
    private function release(ExecutionLock $lock): void
    {
        try {
            $this->locks->release($lock);
        } catch (\Throwable) {
            // A failed release must not mask the execution's result; the TTL will reclaim the lock.
        }
    }
}
