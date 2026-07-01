<?php

declare(strict_types=1);

namespace Nizam\Runtime\Execution\Application\ValueObject;

use Nizam\Kernel\Domain\ValueObject;
use Nizam\Runtime\Execution\Application\Dto\ExecutionTimelineView;
use Nizam\Runtime\Execution\Domain\Execution;
use Nizam\Runtime\Execution\Domain\ExecutionId;
use Nizam\Runtime\Execution\Domain\ExecutionState;
use Nizam\Runtime\Execution\Domain\ValueObject\CostSnapshot;
use Nizam\Runtime\Execution\Domain\ValueObject\PerformanceSnapshot;

/**
 * The immutable outcome the {@see \Nizam\Runtime\Execution\Application\ExecutionEngine} returns for one run.
 *
 * A result reports the {@see ExecutionId} that ran, its final {@see ExecutionState}, the manager's
 * decision (when the caller — the Master Orchestrator — attaches one), the accrued {@see CostSnapshot}
 * and {@see PerformanceSnapshot}, and the human-readable {@see ExecutionTimelineView}. The manager
 * decision is modelled here as an optional scalar-only map rather than an Orchestration type so the
 * Execution application layer depends only on the Execution domain; the orchestrator projects its own
 * decision value object into this shape. Being a value object, the result is compared by value and
 * carries no behavior beyond projection helpers.
 */
final class ExecutionResult implements ValueObject
{
    /**
     * @param ExecutionId              $executionId     The execution that ran.
     * @param ExecutionState           $finalState      The terminal or final observed state.
     * @param array<string, mixed>|null $managerDecision The manager's decision as a scalar map, when attached.
     * @param CostSnapshot             $cost            The accrued cost.
     * @param PerformanceSnapshot      $performance     The accrued performance.
     * @param ExecutionTimelineView    $timelineView    The human-readable path taken.
     */
    public function __construct(
        private readonly ExecutionId $executionId,
        private readonly ExecutionState $finalState,
        private readonly ?array $managerDecision,
        private readonly CostSnapshot $cost,
        private readonly PerformanceSnapshot $performance,
        private readonly ExecutionTimelineView $timelineView,
    ) {
    }

    /**
     * Project a completed (or terminal) execution into its result, optionally attaching a decision.
     *
     * @param Execution                 $execution       The execution to project.
     * @param array<string, mixed>|null $managerDecision The manager's decision as a scalar map, when known.
     */
    public static function fromExecution(Execution $execution, ?array $managerDecision = null): self
    {
        return new self(
            executionId: $execution->executionId(),
            finalState: $execution->state(),
            managerDecision: $managerDecision,
            cost: $execution->cost(),
            performance: $execution->performance(),
            timelineView: ExecutionTimelineView::fromDomain(
                $execution->executionId(),
                $execution->timeline(),
            ),
        );
    }

    /**
     * Return a copy of this result carrying the given manager decision.
     *
     * @param array<string, mixed> $managerDecision The manager's decision as a scalar map.
     */
    public function withManagerDecision(array $managerDecision): self
    {
        return new self(
            $this->executionId,
            $this->finalState,
            $managerDecision,
            $this->cost,
            $this->performance,
            $this->timelineView,
        );
    }

    /**
     * The execution that ran.
     */
    public function executionId(): ExecutionId
    {
        return $this->executionId;
    }

    /**
     * The terminal or final observed state.
     */
    public function finalState(): ExecutionState
    {
        return $this->finalState;
    }

    /**
     * Whether the execution reached a successful terminal state.
     */
    public function isCompleted(): bool
    {
        return $this->finalState === ExecutionState::Completed;
    }

    /**
     * The manager's decision as a scalar map, when attached.
     *
     * @return array<string, mixed>|null
     */
    public function managerDecision(): ?array
    {
        return $this->managerDecision;
    }

    /**
     * The accrued cost.
     */
    public function cost(): CostSnapshot
    {
        return $this->cost;
    }

    /**
     * The accrued performance.
     */
    public function performance(): PerformanceSnapshot
    {
        return $this->performance;
    }

    /**
     * The human-readable path taken.
     */
    public function timelineView(): ExecutionTimelineView
    {
        return $this->timelineView;
    }

    /**
     * Structural equality across every attribute.
     */
    public function equals(ValueObject $other): bool
    {
        return $other instanceof self
            && $other->executionId->equals($this->executionId)
            && $other->finalState === $this->finalState
            && $other->managerDecision === $this->managerDecision
            && $other->cost->equals($this->cost)
            && $other->performance->equals($this->performance)
            && $other->timelineView->equals($this->timelineView);
    }

    /**
     * A scalar-only representation suitable for JSON serialization and transport.
     *
     * @return array{
     *     executionId: string,
     *     finalState: string,
     *     managerDecision: array<string, mixed>|null,
     *     cost: array{tokens: int, currencyMicros: int, providerBreakdown: array<string, int>},
     *     performance: array{wallMs: int, cpuMs: int|null, stepCount: int, retryCount: int},
     *     timeline: list<array{state: string, at: string, note: string}>
     * }
     */
    public function toArray(): array
    {
        return [
            'executionId' => $this->executionId->toString(),
            'finalState' => $this->finalState->value,
            'managerDecision' => $this->managerDecision,
            'cost' => $this->cost->toArray(),
            'performance' => $this->performance->toArray(),
            'timeline' => $this->timelineView->entries(),
        ];
    }
}
