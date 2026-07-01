<?php

declare(strict_types=1);

namespace Nizam\Runtime\Orchestration\Service;

use Nizam\Runtime\Execution\Application\Port\WorkerDispatcher;
use Nizam\Runtime\Execution\Application\ValueObject\ExecutionRequest;
use Nizam\Runtime\Execution\Domain\ExecutionStep;
use Nizam\Runtime\Execution\Domain\ValueObject\WorkerResult;
use Nizam\Runtime\Orchestration\Exception\OrchestrationException;

/**
 * A request-scoped {@see WorkerDispatcher} that replays worker results the coordinator already produced.
 *
 * The spec-mandated dispatch modes (Sequential, Parallel fan-out, Collaborative re-entry) live in the
 * {@see \Nizam\Runtime\Orchestration\WorkerCoordinator}, which the {@see \Nizam\Runtime\Orchestration\MasterOrchestrator}
 * runs *before* the {@see \Nizam\Runtime\Execution\Application\ExecutionEngine}, collecting one
 * {@see WorkerResult} per planned step. The engine still drives each step through the
 * {@see WorkerDispatcher} port so the aggregate folds cost, performance, and timeline in the normal way;
 * this adapter bridges the two by handing back the coordinator's precomputed result for each step, keyed
 * by {@see \Nizam\Runtime\Execution\Domain\ExecutionStepId}. Coordination therefore happens exactly once,
 * through the coordinator, and the engine replays it deterministically — dispatching a step the
 * coordinator never ran is a programming error and raises.
 */
final class CoordinatedWorkerDispatcher implements WorkerDispatcher
{
    /**
     * @param array<string, WorkerResult> $resultsByStepId The coordinator's results, keyed by step id string.
     */
    public function __construct(
        private readonly array $resultsByStepId,
    ) {
    }

    /**
     * {@inheritDoc}
     *
     * @throws OrchestrationException When the step has no coordinator-produced result (programming error).
     */
    public function dispatch(ExecutionStep $step, ExecutionRequest $request): WorkerResult
    {
        $key = $step->stepId()->toString();
        if (!isset($this->resultsByStepId[$key])) {
            throw OrchestrationException::workerPluginMissing($step->workerRef());
        }

        return $this->resultsByStepId[$key];
    }
}
