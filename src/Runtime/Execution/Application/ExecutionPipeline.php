<?php

declare(strict_types=1);

namespace Nizam\Runtime\Execution\Application;

use Nizam\Runtime\Execution\Application\Stage\AssignStage;
use Nizam\Runtime\Execution\Application\Stage\FinalizeStage;
use Nizam\Runtime\Execution\Application\Stage\PipelineStage;
use Nizam\Runtime\Execution\Application\Stage\PlanStage;
use Nizam\Runtime\Execution\Application\Stage\ReviewStage;
use Nizam\Runtime\Execution\Application\Stage\RunStage;
use Nizam\Runtime\Execution\Application\Stage\ValidateStage;
use Nizam\Runtime\Execution\Domain\Execution;
use Nizam\Runtime\Execution\Domain\ExecutionState;
use Nizam\Runtime\Execution\Domain\ExecutionStateMachine;
use Throwable;

/**
 * Drives one {@see Execution} through the ordered execution stages, honoring the state machine.
 *
 * The pipeline runs its {@see PipelineStage}s in order — Validate, Plan, Assign, Run, Review, Finalize
 * — each advancing the aggregate one legal step through {@see ExecutionState}. Every mutation is
 * guarded by the aggregate's own {@see ExecutionStateMachine} calls, so an illegal move (or any stage
 * error, including a timeout) throws; the pipeline catches it and short-circuits the execution to
 * {@see ExecutionState::Failed} (recording the reason) when failing is legal from the current state.
 * The pipeline never persists or publishes — the {@see ExecutionEngine} owns the transaction and lock
 * — and it never swallows an error silently: it always reflects the outcome in the aggregate's state
 * and timeline. It is stateless across runs and safe to reuse.
 */
final class ExecutionPipeline
{
    /** @var list<PipelineStage> */
    private readonly array $stages;

    /**
     * @param list<PipelineStage> $stages The ordered stages; when empty, the default six-stage pipeline is used.
     */
    public function __construct(array $stages = [])
    {
        $this->stages = $stages === [] ? self::defaultStages() : array_values($stages);
    }

    /**
     * Build the default, spec-mandated six-stage pipeline (Validate→Plan→Assign→Run→Review→Finalize).
     *
     * The default stages depend only on the Execution application layer's own collaborators, so the
     * pipeline runs end-to-end with the deterministic default planner and dispatcher and no
     * orchestration layer present. Callers may inject alternative stages (for example a manager-driven
     * planner/dispatcher) through the constructor.
     *
     * @return list<PipelineStage>
     */
    public static function defaultStages(): array
    {
        return [
            new ValidateStage(new ExecutionValidator()),
            new PlanStage(),
            new AssignStage(),
            new RunStage(),
            new ReviewStage(),
            new FinalizeStage(),
        ];
    }

    /**
     * Run the execution carried on the context through every stage, short-circuiting on error.
     *
     * On success the execution ends in the state the final stage left it in (Completed for the default
     * pipeline). On any stage error, the execution is driven to {@see ExecutionState::Failed} when that
     * transition is legal from its current state, with the stage name and error message recorded as the
     * failure reason; the originating throwable is not re-thrown so the engine can inspect the failed
     * aggregate and decide on retry or recovery.
     *
     * @param PipelineContext $context The request-scoped carrier holding the live execution.
     *
     * @return Execution The same, now-advanced execution aggregate.
     */
    public function run(PipelineContext $context): Execution
    {
        $execution = $context->execution();

        foreach ($this->stages as $stage) {
            try {
                $stage->process($context);
            } catch (Throwable $error) {
                $this->failFrom($execution, $context, $stage->name(), $error->getMessage());

                return $execution;
            }
        }

        return $execution;
    }

    /**
     * Drive the execution to {@see ExecutionState::Failed} when that is legal from its current state.
     *
     * If failing is not legal from where the execution stopped (for example it is already terminal),
     * the aggregate is left as-is so the state machine is never violated.
     */
    private function failFrom(
        Execution $execution,
        PipelineContext $context,
        string $stageName,
        string $message,
    ): void {
        if (!ExecutionStateMachine::canTransition($execution->state(), ExecutionState::Failed)) {
            return;
        }

        $reason = sprintf('Stage "%s" failed: %s', $stageName, $message);
        $execution->fail($reason, $context->clock());
    }
}
