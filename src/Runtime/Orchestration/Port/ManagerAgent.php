<?php

declare(strict_types=1);

namespace Nizam\Runtime\Orchestration\Port;

use Nizam\Platform\Plugin\Contract\ManagerPlugin;
use Nizam\Runtime\Orchestration\ValueObject\ConfidenceAssessment;
use Nizam\Runtime\Orchestration\ValueObject\ExecutionContext;
use Nizam\Runtime\Orchestration\ValueObject\ManagerDecision;
use Nizam\Runtime\Orchestration\ValueObject\MergedResult;
use Nizam\Runtime\Orchestration\ValueObject\OrchestrationRequest;
use Nizam\Runtime\Orchestration\ValueObject\WorkTask;

/**
 * The port that drives a resolved {@see ManagerPlugin}'s two runtime responsibilities: planning the work
 * and deciding on the result.
 *
 * The plugin-kind contract ({@see ManagerPlugin}) identifies *which* manager and the roles it governs;
 * how a manager plans a request into tasks and how it decides on the merged, scored output are runtime
 * concerns the platform owns, not part of the SDK contract. The
 * {@see \Nizam\Runtime\Orchestration\MasterOrchestrator} depends only on this interface: it asks the
 * agent to {@see self::plan()} a request into {@see WorkTask}s (which the
 * {@see \Nizam\Runtime\Orchestration\WorkerCoordinator} then dispatches) and to {@see self::decide()} on
 * the {@see MergedResult} once workers have run, yielding a {@see ManagerDecision}. A manager never talks
 * to a user or a worker directly — it only ever reads merged results and returns tasks or a decision. A
 * deterministic in-memory adapter serves tests and safe defaults; the production adapter drives the
 * plugin sandbox.
 */
interface ManagerAgent
{
    /**
     * Plan a request into the ordered tasks the coordinator should dispatch.
     *
     * @param ManagerPlugin        $manager The resolved manager plugin leading the work.
     * @param OrchestrationRequest $request The originating request.
     * @param ExecutionContext     $context The request-scoped context.
     *
     * @return list<WorkTask> The ordered, non-empty tasks to dispatch.
     */
    public function plan(ManagerPlugin $manager, OrchestrationRequest $request, ExecutionContext $context): array;

    /**
     * Decide on the merged, scored worker output.
     *
     * @param ManagerPlugin        $manager    The resolved manager plugin leading the work.
     * @param MergedResult         $merged     The merged worker output.
     * @param ConfidenceAssessment $assessment The Runtime's confidence assessment of the merged output.
     * @param ExecutionContext     $context    The request-scoped context.
     *
     * @return ManagerDecision The manager's verdict.
     */
    public function decide(
        ManagerPlugin $manager,
        MergedResult $merged,
        ConfidenceAssessment $assessment,
        ExecutionContext $context,
    ): ManagerDecision;
}
