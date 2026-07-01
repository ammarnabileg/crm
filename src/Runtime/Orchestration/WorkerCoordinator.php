<?php

declare(strict_types=1);

namespace Nizam\Runtime\Orchestration;

use Nizam\Platform\Support\Assert;
use Nizam\Runtime\Execution\Domain\ValueObject\WorkerResult;
use Nizam\Runtime\Orchestration\Exception\OrchestrationException;
use Nizam\Runtime\Orchestration\Port\WorkerInvoker;
use Nizam\Runtime\Orchestration\ValueObject\DispatchMode;
use Nizam\Runtime\Orchestration\ValueObject\ExecutionContext;
use Nizam\Runtime\Orchestration\ValueObject\WorkerAssignment;

/**
 * The single seam through which every worker is invoked, in the three spec-mandated dispatch modes.
 *
 * Workers never talk to users, to automation engines, or to each other: the coordinator resolves each
 * {@see WorkerAssignment} through the injected {@see WorkerInvoker}, so a worker only ever receives its
 * task and the read-only {@see ExecutionContext} — it holds no reference to another worker or to this
 * coordinator, which makes worker-to-worker calls structurally impossible.
 *
 * The three modes:
 * - {@see DispatchMode::Sequential} — invoke assignments strictly in order, one at a time.
 * - {@see DispatchMode::Parallel} — a deterministic in-process fan-out: assignments are independent, so
 *   they are invoked and their results collected in submission order. There are no real threads; the
 *   outcome is reproducible. Modelling parallelism as ordered in-process dispatch is a deliberate,
 *   documented simplification (see the phase audit).
 * - {@see DispatchMode::Collaborative} — after the initial batch runs, a bounded re-entry callback may
 *   supply *follow-up* assignments (the manager's response to a worker asking for more help). Those are
 *   satisfied by re-entering this coordinator, never by a worker invoking another worker directly; the
 *   {@see self::MAX_COLLABORATION_ROUNDS} cap bounds the recursion.
 *
 * Dispatch is deterministic given a deterministic invoker.
 */
final class WorkerCoordinator
{
    /**
     * The maximum number of follow-up rounds a collaborative dispatch may run, bounding re-entry.
     */
    public const int MAX_COLLABORATION_ROUNDS = 8;

    /**
     * @param WorkerInvoker $invoker The port that runs a resolved worker against its task.
     */
    public function __construct(
        private readonly WorkerInvoker $invoker,
    ) {
    }

    /**
     * Dispatch a batch of assignments in the given mode, returning their results in submission order.
     *
     * For {@see DispatchMode::Sequential} and {@see DispatchMode::Parallel} the two behave identically at
     * the result level — deterministic, ordered collection — differing only in intent (parallel promises
     * the assignments are independent). {@see DispatchMode::Collaborative} without a follow-up resolver
     * behaves like a single sequential round; use {@see self::dispatchCollaborative()} to supply the
     * bounded re-entry that grants collaboration.
     *
     * @param list<WorkerAssignment> $assignments The resolved worker/task pairs to run (non-empty).
     * @param DispatchMode           $mode        The dispatch strategy.
     * @param ExecutionContext       $context     The request-scoped context handed to each worker.
     *
     * @return list<WorkerResult> The results, one per assignment, in submission order.
     */
    public function dispatch(array $assignments, DispatchMode $mode, ExecutionContext $context): array
    {
        Assert::that($assignments !== [], 'A dispatch requires at least one worker assignment.');
        $this->assertAssignments($assignments);

        if ($mode === DispatchMode::Collaborative) {
            return $this->dispatchCollaborative($assignments, $context, static fn (): array => []);
        }

        return $this->runBatch($assignments, $context);
    }

    /**
     * Run a collaborative dispatch: the initial batch, then bounded rounds of manager-supplied follow-ups.
     *
     * The `$followUp` resolver is the *only* way additional workers enter the coordinator. After each
     * round it is handed every result so far and may return further {@see WorkerAssignment}s — the
     * manager's answer to a worker that asked for more help. Returning an empty list ends the
     * collaboration. This is how "a worker requests more workers" is satisfied: by re-entering this
     * coordinator through the manager, never by one worker calling another. The number of rounds is
     * capped by {@see self::MAX_COLLABORATION_ROUNDS}.
     *
     * @param list<WorkerAssignment>                          $assignments The initial resolved assignments (non-empty).
     * @param ExecutionContext                                $context     The request-scoped context.
     * @param callable(list<WorkerResult>): list<WorkerAssignment> $followUp The bounded re-entry resolver.
     *
     * @return list<WorkerResult> Every result, across all rounds, in the order it was produced.
     */
    public function dispatchCollaborative(array $assignments, ExecutionContext $context, callable $followUp): array
    {
        Assert::that($assignments !== [], 'A collaborative dispatch requires at least one worker assignment.');
        $this->assertAssignments($assignments);

        $results = $this->runBatch($assignments, $context);

        for ($round = 0; $round < self::MAX_COLLABORATION_ROUNDS; ++$round) {
            $next = $followUp($results);
            if ($next === []) {
                break;
            }
            $this->assertAssignments($next);

            $results = [...$results, ...$this->runBatch($next, $context)];
        }

        return $results;
    }

    /**
     * Invoke every assignment in order through the worker invoker, collecting results in submission order.
     *
     * @param list<WorkerAssignment> $assignments
     *
     * @return list<WorkerResult>
     */
    private function runBatch(array $assignments, ExecutionContext $context): array
    {
        $results = [];
        foreach ($assignments as $assignment) {
            $this->assertPermitted($assignment, $context);
            $results[] = $this->invoker->invoke($assignment->worker(), $assignment->task(), $context);
        }

        return $results;
    }

    /**
     * Validate that the given list contains only {@see WorkerAssignment} instances.
     *
     * @param array<array-key, mixed> $assignments
     */
    private function assertAssignments(array $assignments): void
    {
        foreach ($assignments as $assignment) {
            Assert::that(
                $assignment instanceof WorkerAssignment,
                'A dispatch batch must contain only WorkerAssignment instances.',
            );
        }
    }

    /**
     * Ensure the granted permissions authorise dispatching this worker.
     *
     * The worker's manifest declares the permissions it requires; the request-scoped context carries the
     * permissions the tenant granted. Authorization is enforced here, at the single Runtime seam, so a
     * worker can never run beyond what the tenant allowed.
     *
     * @throws OrchestrationException When the granted set does not cover the worker's required set.
     */
    private function assertPermitted(WorkerAssignment $assignment, ExecutionContext $context): void
    {
        $required = $assignment->worker()->manifest()->requiredPermissionSet();
        if (!$context->grantedPermissions()->grants($required)) {
            throw OrchestrationException::permissionDenied($assignment->task()->workerRef());
        }
    }
}
