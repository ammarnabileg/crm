<?php

declare(strict_types=1);

namespace Nizam\Runtime\Infrastructure;

use Nizam\Kernel\Domain\Clock;
use Nizam\Runtime\Execution\Application\ExecutionEngine;
use Nizam\Runtime\Execution\Application\ExecutionPipeline;
use Nizam\Runtime\Execution\Application\ExecutionValidator;
use Nizam\Runtime\Execution\Application\Port\ExecutionPlanner;
use Nizam\Runtime\Execution\Application\Port\WorkerDispatcher;
use Nizam\Runtime\Execution\Application\RetryEngine;
use Nizam\Runtime\Execution\Application\Stage\AssignStage;
use Nizam\Runtime\Execution\Application\Stage\FinalizeStage;
use Nizam\Runtime\Execution\Application\Stage\PlanStage;
use Nizam\Runtime\Execution\Application\Stage\ReviewStage;
use Nizam\Runtime\Execution\Application\Stage\RunStage;
use Nizam\Runtime\Execution\Application\Stage\ValidateStage;
use Nizam\Runtime\Execution\Domain\Port\ExecutionEventPublisher;
use Nizam\Runtime\Execution\Domain\Port\ExecutionEventStore;
use Nizam\Runtime\Execution\Domain\Port\ExecutionLockManager;
use Nizam\Runtime\Execution\Domain\Port\ExecutionRepository;
use Nizam\Runtime\Orchestration\Port\ExecutionEngineFactory;

/**
 * The production {@see ExecutionEngineFactory}: it assembles an {@see ExecutionEngine} bound to shared
 * persistence collaborators and the request-scoped planner and dispatcher.
 *
 * The {@see \Nizam\Runtime\Orchestration\MasterOrchestrator} must not know how the engine's
 * persistence, locking, and event publication are wired — that is Infrastructure's job. This factory
 * holds those shared collaborators (the repository, the append-only event store, the lock manager, the
 * event publisher, the retry engine, and the clock — bound by the {@see RuntimeServiceProvider} to the
 * in-memory or PDO adapters) and, on {@see self::build()}, constructs a genuine engine with a fresh
 * validated stage pipeline and the per-request planner and dispatcher stamped in. Because the store and
 * lock manager are shared across builds, two runs of the same execution id serialize and short-circuit
 * on idempotency exactly as in production, independent of the chosen persistence driver.
 */
final class RuntimeExecutionEngineFactory implements ExecutionEngineFactory
{
    /**
     * @param ExecutionRepository     $repository  The shared snapshot store executions are saved to and read from.
     * @param ExecutionEventStore     $eventStore  The shared append-only event stream each attempt is recorded in.
     * @param ExecutionLockManager    $locks       The shared mutual-exclusion port serializing work per execution.
     * @param ExecutionEventPublisher $publisher   The shared port that announces recorded events after commit.
     * @param RetryEngine             $retryEngine The retry decision service.
     * @param Clock                   $clock       The time source every mutation and timeout uses.
     */
    public function __construct(
        private readonly ExecutionRepository $repository,
        private readonly ExecutionEventStore $eventStore,
        private readonly ExecutionLockManager $locks,
        private readonly ExecutionEventPublisher $publisher,
        private readonly RetryEngine $retryEngine,
        private readonly Clock $clock,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function build(ExecutionPlanner $planner, WorkerDispatcher $dispatcher): ExecutionEngine
    {
        $pipeline = new ExecutionPipeline([
            new ValidateStage(new ExecutionValidator()),
            new PlanStage(),
            new AssignStage(),
            new RunStage(),
            new ReviewStage(),
            new FinalizeStage(),
        ]);

        return new ExecutionEngine(
            repository: $this->repository,
            eventStore: $this->eventStore,
            locks: $this->locks,
            publisher: $this->publisher,
            pipeline: $pipeline,
            retryEngine: $this->retryEngine,
            planner: $planner,
            dispatcher: $dispatcher,
            clock: $this->clock,
        );
    }
}
