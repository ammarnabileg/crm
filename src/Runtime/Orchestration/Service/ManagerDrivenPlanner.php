<?php

declare(strict_types=1);

namespace Nizam\Runtime\Orchestration\Service;

use Nizam\Runtime\Execution\Application\Port\ExecutionPlanner;
use Nizam\Runtime\Execution\Application\ValueObject\ExecutionRequest;
use Nizam\Runtime\Execution\Domain\ExecutionStep;

/**
 * A request-scoped {@see ExecutionPlanner} that returns the exact steps a Manager plugin already planned.
 *
 * The {@see \Nizam\Runtime\Orchestration\MasterOrchestrator} asks the resolved manager (via its
 * {@see \Nizam\Runtime\Orchestration\Port\ManagerAgent}) to plan the request into work tasks, turns each
 * task into an {@see ExecutionStep}, and hands the resulting list to the {@see \Nizam\Runtime\Execution\Application\ExecutionEngine}
 * through this planner. Because the steps are precomputed for one specific request, this adapter is
 * constructed per request and simply echoes them back when the engine's plan/assign stages ask for them
 * — the manager decides *what* the work is, the engine decides *how* it is driven. The engine also
 * re-plans on a recovered attempt; returning the same steps keeps a resumed run deterministic.
 */
final class ManagerDrivenPlanner implements ExecutionPlanner
{
    /**
     * @param list<ExecutionStep> $steps The steps the manager planned for this request (non-empty).
     */
    public function __construct(
        private readonly array $steps,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function plan(ExecutionRequest $request): array
    {
        return $this->steps;
    }
}
