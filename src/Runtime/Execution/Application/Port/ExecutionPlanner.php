<?php

declare(strict_types=1);

namespace Nizam\Runtime\Execution\Application\Port;

use Nizam\Runtime\Execution\Application\ValueObject\ExecutionRequest;
use Nizam\Runtime\Execution\Domain\ExecutionStep;

/**
 * The port through which the Plan/Assign stages obtain the steps an execution should run.
 *
 * Planning — deciding which units of work an execution decomposes into and which worker each is
 * dispatched to — is a manager concern that lives outside the Execution application layer (the Master
 * Orchestrator resolves the manager plugin and asks it to plan). The pipeline depends only on this
 * port: given the validated {@see ExecutionRequest}, it returns the ordered {@see ExecutionStep}s to
 * assign. The Execution application layer ships a deterministic default planner so it is testable in
 * isolation; the orchestration layer provides the manager-driven implementation.
 */
interface ExecutionPlanner
{
    /**
     * Plan the ordered steps an execution should run for the given request.
     *
     * @param ExecutionRequest $request The validated request being executed.
     *
     * @return list<ExecutionStep> The ordered, non-empty steps to assign.
     */
    public function plan(ExecutionRequest $request): array;
}
