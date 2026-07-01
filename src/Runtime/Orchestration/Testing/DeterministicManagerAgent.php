<?php

declare(strict_types=1);

namespace Nizam\Runtime\Orchestration\Testing;

use Nizam\Platform\Plugin\Contract\ManagerPlugin;
use Nizam\Runtime\Orchestration\Port\ManagerAgent;
use Nizam\Runtime\Orchestration\ValueObject\ConfidenceAssessment;
use Nizam\Runtime\Orchestration\ValueObject\ExecutionContext;
use Nizam\Runtime\Orchestration\ValueObject\ManagerDecision;
use Nizam\Runtime\Orchestration\ValueObject\MergedResult;
use Nizam\Runtime\Orchestration\ValueObject\OrchestrationRequest;
use Nizam\Runtime\Orchestration\ValueObject\WorkTask;

/**
 * A deterministic, dependency-free {@see ManagerAgent} for tests and safe defaults.
 *
 * It plans a request into a fixed number of tasks (default one), each bound to a configured worker
 * reference and carrying the request's payload, so a given request always yields the same plan. It
 * decides by translating the Runtime's {@see ConfidenceAssessment} into the matching outcome —
 * approve when acceptable, request more workers or a retry when the assessment asks for them, otherwise
 * reject — which mirrors how a real manager would honour the Runtime's confidence signals while keeping
 * the decision the manager's own. No I/O, no clock, no randomness.
 */
final class DeterministicManagerAgent implements ManagerAgent
{
    /**
     * @param string $workerRef The worker reference every planned task is bound to.
     * @param int    $taskCount The number of tasks the manager plans per request (>= 1).
     * @param float  $acceptThreshold The score at or above which a clean result is approved, in [0, 1].
     */
    public function __construct(
        private readonly string $workerRef = 'runtime.fake-worker',
        private readonly int $taskCount = 1,
        private readonly float $acceptThreshold = 0.75,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function plan(ManagerPlugin $manager, OrchestrationRequest $request, ExecutionContext $context): array
    {
        $tasks = [];
        for ($index = 0; $index < max(1, $this->taskCount); ++$index) {
            $tasks[] = new WorkTask(
                taskId: sprintf('task-%d', $index + 1),
                capability: $request->intentRef(),
                workerRef: $this->workerRef,
                payload: $request->payload(),
                intent: sprintf('Fulfil "%s" (part %d)', $request->intentRef(), $index + 1),
            );
        }

        return $tasks;
    }

    /**
     * {@inheritDoc}
     */
    public function decide(
        ManagerPlugin $manager,
        MergedResult $merged,
        ConfidenceAssessment $assessment,
        ExecutionContext $context,
    ): ManagerDecision {
        $evidence = $merged->evidence();

        if ($assessment->needsRetry()) {
            return ManagerDecision::requestRetry(
                sprintf('Manager "%s" requests a retry: %s', $manager->manifest()->name(), $assessment->rationale()),
                $evidence,
                $assessment,
                $merged,
            );
        }

        if ($assessment->needsMoreWorkers()) {
            return ManagerDecision::requestMoreWorkers(
                sprintf('Manager "%s" requests more workers: %s', $manager->manifest()->name(), $assessment->rationale()),
                $evidence,
                $assessment,
                $merged,
            );
        }

        if ($assessment->isAcceptable($this->acceptThreshold)) {
            return ManagerDecision::approve(
                sprintf('Manager "%s" approves the work: %s', $manager->manifest()->name(), $assessment->rationale()),
                $evidence,
                $assessment,
                $merged,
            );
        }

        return ManagerDecision::reject(
            sprintf('Manager "%s" rejects the work: %s', $manager->manifest()->name(), $assessment->rationale()),
            $evidence,
            $assessment,
            $merged,
        );
    }
}
