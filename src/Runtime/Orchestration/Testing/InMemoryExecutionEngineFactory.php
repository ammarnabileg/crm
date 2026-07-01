<?php

declare(strict_types=1);

namespace Nizam\Runtime\Orchestration\Testing;

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
use Nizam\Runtime\Orchestration\Port\ExecutionEngineFactory;

/**
 * A real, single-process {@see ExecutionEngineFactory} for the Runtime's own tests and safe defaults.
 *
 * It holds the shared execution collaborators — the in-memory snapshot/event/publish store, the
 * in-memory lock manager, the retry engine, and a validated stage pipeline — and, on {@see self::create()},
 * assembles a genuine {@see ExecutionEngine} bound to the request-scoped planner and dispatcher the
 * {@see \Nizam\Runtime\Orchestration\MasterOrchestrator} supplies. Because the store and lock manager are
 * shared across calls, two runs of the same execution id serialize and short-circuit on idempotency just
 * as they would in production, letting the orchestration tests exercise the full engine end-to-end
 * without any real persistence. The production factory (a future infrastructure phase) is wired the same
 * way over PDO adapters.
 */
final class InMemoryExecutionEngineFactory implements ExecutionEngineFactory
{
    /**
     * @param InMemoryExecutionStore           $store The shared snapshot/event/publisher backing store.
     * @param InMemoryExecutionLockManager     $locks The shared single-process lock manager.
     * @param RetryEngine                      $retryEngine The retry decision service.
     * @param Clock                            $clock The time source every mutation and timeout uses.
     */
    public function __construct(
        private readonly InMemoryExecutionStore $store,
        private readonly InMemoryExecutionLockManager $locks,
        private readonly RetryEngine $retryEngine,
        private readonly Clock $clock,
    ) {
    }

    /**
     * Build a factory with fresh in-memory collaborators around the given clock.
     *
     * A convenience for tests and safe-default wiring: it constructs the shared store, lock manager, and
     * retry engine so a caller needs only a clock.
     */
    public static function create(Clock $clock): self
    {
        return new self(
            new InMemoryExecutionStore(),
            new InMemoryExecutionLockManager($clock),
            new RetryEngine(),
            $clock,
        );
    }

    /**
     * The shared backing store, exposed so tests can assert what was persisted and published.
     */
    public function store(): InMemoryExecutionStore
    {
        return $this->store;
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
            repository: $this->store,
            eventStore: $this->store,
            locks: $this->locks,
            publisher: $this->store,
            pipeline: $pipeline,
            retryEngine: $this->retryEngine,
            planner: $planner,
            dispatcher: $dispatcher,
            clock: $this->clock,
        );
    }
}
