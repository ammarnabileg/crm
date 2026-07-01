<?php

declare(strict_types=1);

namespace Nizam\Runtime\Orchestration\ValueObject;

use Nizam\Kernel\Domain\ValueObject;
use Nizam\Runtime\Execution\Application\Dto\ExecutionTimelineView;
use Nizam\Runtime\Execution\Application\ValueObject\ExecutionResult;
use Nizam\Runtime\Execution\Domain\ExecutionId;
use Nizam\Runtime\Execution\Domain\ExecutionState;

/**
 * The immutable outcome the {@see \Nizam\Runtime\Orchestration\MasterOrchestrator} returns for one
 * request.
 *
 * A response reports the {@see ExecutionId} that ran, its final {@see ExecutionState}, the
 * {@see ManagerDecision} the manager reached (present on a normally-completed run; absent only when the
 * request was rejected before an execution began), and the underlying {@see ExecutionResult} carrying
 * the accrued cost, performance, and timeline. It is the single artefact a caller inspects to learn
 * both *what the platform decided* (the manager's verdict) and *how it got there* (the execution's
 * observable state and timeline). Being a value object it is immutable and compared by value.
 */
final class OrchestrationResponse implements ValueObject
{
    /**
     * @param ExecutionId          $executionId     The execution that ran.
     * @param ExecutionState       $finalState      The final observed execution state.
     * @param ManagerDecision|null $managerDecision The manager's decision, when one was reached.
     * @param ExecutionResult      $executionResult The underlying execution outcome (cost/perf/timeline).
     */
    public function __construct(
        private readonly ExecutionId $executionId,
        private readonly ExecutionState $finalState,
        private readonly ?ManagerDecision $managerDecision,
        private readonly ExecutionResult $executionResult,
    ) {
    }

    /**
     * Build a response from an execution result and the manager's decision (when reached).
     *
     * @param ExecutionResult      $result   The underlying execution outcome.
     * @param ManagerDecision|null $decision The manager's decision, or null when none was reached.
     */
    public static function fromExecution(ExecutionResult $result, ?ManagerDecision $decision): self
    {
        return new self(
            $result->executionId(),
            $result->finalState(),
            $decision,
            $decision !== null ? $result->withManagerDecision($decision->toArray()) : $result,
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
     * The final observed execution state.
     */
    public function finalState(): ExecutionState
    {
        return $this->finalState;
    }

    /**
     * The manager's decision, when one was reached.
     */
    public function managerDecision(): ?ManagerDecision
    {
        return $this->managerDecision;
    }

    /**
     * The manager's decision outcome, when a decision was reached.
     */
    public function outcome(): ?DecisionOutcome
    {
        return $this->managerDecision?->outcome();
    }

    /**
     * The underlying execution outcome (cost/perf/timeline).
     */
    public function executionResult(): ExecutionResult
    {
        return $this->executionResult;
    }

    /**
     * The human-readable path the execution took.
     */
    public function timeline(): ExecutionTimelineView
    {
        return $this->executionResult->timelineView();
    }

    /**
     * Whether the execution reached a successful terminal state.
     */
    public function isCompleted(): bool
    {
        return $this->finalState === ExecutionState::Completed;
    }

    /**
     * Structural equality across every attribute.
     */
    public function equals(ValueObject $other): bool
    {
        return $other instanceof self
            && $other->executionId->equals($this->executionId)
            && $other->finalState === $this->finalState
            && $this->decisionEquals($other->managerDecision)
            && $other->executionResult->equals($this->executionResult);
    }

    /**
     * Compare the optional manager decision by value, treating two nulls as equal.
     */
    private function decisionEquals(?ManagerDecision $other): bool
    {
        if ($this->managerDecision === null || $other === null) {
            return $this->managerDecision === null && $other === null;
        }

        return $this->managerDecision->equals($other);
    }

    /**
     * A scalar-only representation suitable for transport back to the caller.
     *
     * @return array{
     *     executionId: string,
     *     finalState: string,
     *     managerDecision: array<string, mixed>|null,
     *     execution: array<string, mixed>
     * }
     */
    public function toArray(): array
    {
        return [
            'executionId' => $this->executionId->toString(),
            'finalState' => $this->finalState->value,
            'managerDecision' => $this->managerDecision?->toArray(),
            'execution' => $this->executionResult->toArray(),
        ];
    }
}
