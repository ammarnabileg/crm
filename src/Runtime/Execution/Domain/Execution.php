<?php

declare(strict_types=1);

namespace Nizam\Runtime\Execution\Domain;

use DateTimeImmutable;
use Nizam\Kernel\Domain\AggregateRoot;
use Nizam\Kernel\Domain\Clock;
use Nizam\Kernel\Domain\DomainEvent;
use Nizam\Platform\Support\Assert;
use Nizam\Runtime\Execution\Domain\Event\ExecutionApproved;
use Nizam\Runtime\Execution\Domain\Event\ExecutionCancelled;
use Nizam\Runtime\Execution\Domain\Event\ExecutionCompleted;
use Nizam\Runtime\Execution\Domain\Event\ExecutionFailed;
use Nizam\Runtime\Execution\Domain\Event\ExecutionPlanned;
use Nizam\Runtime\Execution\Domain\Event\ExecutionRecovered;
use Nizam\Runtime\Execution\Domain\Event\ExecutionRejected;
use Nizam\Runtime\Execution\Domain\Event\ExecutionRetried;
use Nizam\Runtime\Execution\Domain\Event\ExecutionSentToReview;
use Nizam\Runtime\Execution\Domain\Event\ExecutionStarted;
use Nizam\Runtime\Execution\Domain\Event\ExecutionStepCompleted;
use Nizam\Runtime\Execution\Domain\Event\ExecutionStepStarted;
use Nizam\Runtime\Execution\Domain\Event\WorkTaskAssigned;
use Nizam\Runtime\Execution\Domain\Exception\RetryExhausted;
use Nizam\Runtime\Execution\Domain\Exception\UnknownStepException;
use Nizam\Runtime\Execution\Domain\ValueObject\CostSnapshot;
use Nizam\Runtime\Execution\Domain\ValueObject\ExecutionMetadata;
use Nizam\Runtime\Execution\Domain\ValueObject\ExecutionTimeline;
use Nizam\Runtime\Execution\Domain\ValueObject\PerformanceSnapshot;
use Nizam\Runtime\Execution\Domain\ValueObject\RetryPolicy;
use Nizam\Runtime\Execution\Domain\ValueObject\TimeoutPolicy;
use Nizam\Runtime\Execution\Domain\ValueObject\WorkerResult;

/**
 * The event-sourced aggregate representing one run of work through the Runtime.
 *
 * An execution is the only unit the Runtime ever executes. It is driven strictly through the
 * {@see ExecutionStateMachine}: every lifecycle mutator ({@see self::plan()}, {@see self::assign()},
 * {@see self::run()}, {@see self::retry()}, {@see self::toReview()}, {@see self::approve()},
 * {@see self::reject()}, {@see self::complete()}, {@see self::fail()}, {@see self::recover()},
 * {@see self::cancel()}, and the step mutators) first asserts the transition is legal, then records
 * the matching {@see DomainEvent}, then mutates state by applying that event. Because every state
 * change flows through {@see self::applyEvent()}, the aggregate can be rebuilt bit-for-bit from its
 * event stream via {@see self::replay()} — the foundation of recovery, replay, and audit. Cost and
 * performance snapshots are folded forward as steps complete and retries occur. The retry budget is
 * enforced from the {@see RetryPolicy}; exceeding it raises {@see RetryExhausted}. All time comes
 * from the injected {@see Clock}; the aggregate performs no I/O.
 */
final class Execution extends AggregateRoot
{
    /**
     * @param ExecutionId          $id           The execution's identity.
     * @param ExecutionMetadata    $metadata     The descriptive context.
     * @param ExecutionState       $state        The current lifecycle state.
     * @param array<string, ExecutionStep> $steps The steps, keyed by step-id string, in insertion order.
     * @param ExecutionTimeline    $timeline     The human-readable path taken.
     * @param RetryPolicy          $retryPolicy  The retry rules.
     * @param TimeoutPolicy        $timeoutPolicy The time budget.
     * @param CostSnapshot         $cost         The accrued cost.
     * @param PerformanceSnapshot  $performance  The accrued performance.
     * @param int                  $attempts     How many attempts have been made (1-based).
     * @param DateTimeImmutable    $createdAt    When the execution was created.
     * @param DateTimeImmutable    $updatedAt    When it last changed.
     * @param int                  $version      The optimistic-concurrency version.
     */
    private function __construct(
        ExecutionId $id,
        private ExecutionMetadata $metadata,
        private ExecutionState $state,
        private array $steps,
        private ExecutionTimeline $timeline,
        private RetryPolicy $retryPolicy,
        private TimeoutPolicy $timeoutPolicy,
        private CostSnapshot $cost,
        private PerformanceSnapshot $performance,
        private int $attempts,
        private readonly DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
        private int $version,
    ) {
        parent::__construct($id);
    }

    // -----------------------------------------------------------------------------------------
    // Factory
    // -----------------------------------------------------------------------------------------

    /**
     * Start a brand-new execution in {@see ExecutionState::Pending}.
     *
     * Records an {@see ExecutionStarted} event and seeds the timeline with the pending entry.
     *
     * @param ExecutionId       $id            The identity to assign.
     * @param ExecutionMetadata $metadata      The descriptive context (tenant, intent, etc.).
     * @param RetryPolicy       $retryPolicy   The retry rules.
     * @param TimeoutPolicy     $timeoutPolicy The time budget.
     * @param Clock             $clock         Time source.
     */
    public static function start(
        ExecutionId $id,
        ExecutionMetadata $metadata,
        RetryPolicy $retryPolicy,
        TimeoutPolicy $timeoutPolicy,
        Clock $clock,
    ): self {
        $now = $clock->now();

        $execution = new self(
            id: $id,
            metadata: $metadata,
            state: ExecutionState::Pending,
            steps: [],
            timeline: ExecutionTimeline::empty(),
            retryPolicy: $retryPolicy,
            timeoutPolicy: $timeoutPolicy,
            cost: CostSnapshot::zero(),
            performance: PerformanceSnapshot::zero(),
            attempts: 1,
            createdAt: $now,
            updatedAt: $now,
            version: 0,
        );

        $execution->raise(new ExecutionStarted($id, $metadata, $retryPolicy, $timeoutPolicy, $now));

        return $execution;
    }

    /**
     * Rebuild an execution purely from its recorded event stream.
     *
     * Read-only reconstitution: the returned aggregate has the same state, steps, timeline, cost and
     * performance it had when the events were recorded, and carries no pending (unpublished) events.
     * The stream must begin with an {@see ExecutionStarted}.
     *
     * @param list<DomainEvent> $events The full, ordered event stream for one execution.
     *
     * @throws \InvalidArgumentException When the stream is empty or does not start with ExecutionStarted.
     */
    public static function replay(array $events): self
    {
        $first = $events[0] ?? null;
        if (!$first instanceof ExecutionStarted) {
            throw new \InvalidArgumentException(
                'An execution event stream must begin with an ExecutionStarted event.',
            );
        }

        $execution = new self(
            id: $first->executionId(),
            metadata: $first->metadata(),
            state: ExecutionState::Pending,
            steps: [],
            timeline: ExecutionTimeline::empty(),
            retryPolicy: RetryPolicy::default(),
            timeoutPolicy: TimeoutPolicy::default(),
            cost: CostSnapshot::zero(),
            performance: PerformanceSnapshot::zero(),
            attempts: 1,
            createdAt: $first->occurredAt(),
            updatedAt: $first->occurredAt(),
            version: 0,
        );

        foreach ($events as $event) {
            $execution->applyEvent($event);
        }

        return $execution;
    }

    // -----------------------------------------------------------------------------------------
    // Lifecycle mutators
    // -----------------------------------------------------------------------------------------

    /**
     * Move the execution into {@see ExecutionState::Planning}.
     *
     * @param Clock $clock Time source.
     */
    public function plan(Clock $clock): void
    {
        $now = $clock->now();
        ExecutionStateMachine::assert($this->state, ExecutionState::Planning);
        $this->raise(new ExecutionPlanned($this->executionId(), $now));
    }

    /**
     * Assign work steps to the execution, moving it into {@see ExecutionState::Assigned}.
     *
     * @param list<ExecutionStep> $steps The steps to assign (must be non-empty).
     * @param Clock               $clock Time source.
     */
    public function assign(array $steps, Clock $clock): void
    {
        Assert::that($steps !== [], 'An execution must be assigned at least one step.');
        foreach ($steps as $step) {
            Assert::that($step instanceof ExecutionStep, 'assign() accepts only ExecutionStep instances.');
        }

        $now = $clock->now();
        ExecutionStateMachine::assert($this->state, ExecutionState::Assigned);

        $descriptors = [];
        foreach ($steps as $step) {
            $descriptors[] = [
                'stepId' => $step->stepId()->toString(),
                'name' => $step->name(),
                'workerRef' => $step->workerRef(),
            ];
        }

        $this->raise(new WorkTaskAssigned($this->executionId(), $descriptors, $now));
    }

    /**
     * Begin a specific assigned step, taking the execution into {@see ExecutionState::Running}.
     *
     * When the execution is not already Running, this asserts that the Running transition is legal
     * from the current state before recording the step start, so a step can only begin from a state
     * the machine permits (Assigned, Waiting, Retrying, or Recovered).
     *
     * @param ExecutionStepId $stepId The step to start.
     * @param Clock           $clock  Time source.
     *
     * @throws UnknownStepException          When the step does not belong to this execution.
     * @throws \Nizam\Runtime\Execution\Domain\Exception\IllegalExecutionTransition When Running is not
     *                                       reachable from the current state.
     */
    public function beginStep(ExecutionStepId $stepId, Clock $clock): void
    {
        $this->requireStep($stepId);
        if ($this->state !== ExecutionState::Running) {
            ExecutionStateMachine::assert($this->state, ExecutionState::Running);
        }
        $now = $clock->now();
        $this->raise(new ExecutionStepStarted($this->executionId(), $stepId, $now));
    }

    /**
     * Complete a specific step, attaching its worker result and folding its cost/performance in.
     *
     * @param ExecutionStepId $stepId The step to complete.
     * @param WorkerResult    $result The worker's structured output.
     * @param Clock           $clock  Time source.
     *
     * @throws UnknownStepException When the step does not belong to this execution.
     */
    public function completeStep(ExecutionStepId $stepId, WorkerResult $result, Clock $clock): void
    {
        $this->requireStep($stepId);
        $now = $clock->now();
        $this->raise(new ExecutionStepCompleted($this->executionId(), $stepId, $result, $now));
    }

    /**
     * Move the execution into {@see ExecutionState::Running} by beginning its first pending step.
     *
     * Running is an execution-level state that is entered when work actually starts; because the
     * event stream carries no standalone "running" event, this transition is event-sourced through
     * the {@see ExecutionStepStarted} of the first pending step, so it reconstructs faithfully on
     * replay. Requires at least one pending step (an execution always has steps once assigned).
     *
     * @param Clock $clock Time source.
     *
     * @throws UnknownStepException When the execution has no pending step to begin.
     */
    public function run(Clock $clock): void
    {
        ExecutionStateMachine::assert($this->state, ExecutionState::Running);

        $pending = $this->firstPendingStep();
        if ($pending === null) {
            throw UnknownStepException::forId('<pending>', $this->executionId()->toString());
        }

        $this->beginStep($pending->stepId(), $clock);
    }

    /**
     * Move the execution into {@see ExecutionState::Waiting}.
     *
     * Waiting means the execution is running but paused between steps, awaiting a remaining step or an
     * external signal. It is recorded through an {@see ExecutionStepCompleted} carrying the last
     * completed step's result, so it is fully event-sourced and replay-faithful; the transition to
     * Waiting is derived when a step completes while pending steps remain (see
     * {@see self::applyStepCompleted()}).
     *
     * @param ExecutionStepId $lastCompletedStepId The step just completed whose result is recorded.
     * @param WorkerResult    $result              That step's structured output.
     * @param Clock           $clock               Time source.
     *
     * @throws UnknownStepException When the step does not belong to this execution.
     */
    public function awaitStep(ExecutionStepId $lastCompletedStepId, WorkerResult $result, Clock $clock): void
    {
        ExecutionStateMachine::assert($this->state, ExecutionState::Waiting);
        $this->completeStep($lastCompletedStepId, $result, $clock);
    }

    /**
     * Schedule a retry, moving the execution into {@see ExecutionState::Retrying}.
     *
     * Enforces the {@see RetryPolicy}: if the budget is exhausted this raises {@see RetryExhausted}
     * and does not transition. Otherwise it increments the attempt counter, records the retry, and
     * bumps the retry performance counter.
     *
     * @param string $reason The human-readable reason for the retry.
     * @param Clock  $clock  Time source.
     *
     * @throws RetryExhausted When no further attempts are permitted by the policy.
     */
    public function retry(string $reason, Clock $clock): void
    {
        Assert::notEmpty($reason, 'A retry must record a reason.');
        $now = $clock->now();
        ExecutionStateMachine::assert($this->state, ExecutionState::Retrying);

        if (!$this->retryPolicy->shouldRetry($this->attempts)) {
            throw RetryExhausted::forPolicy($this->attempts, $this->retryPolicy->maxAttempts());
        }

        $this->raise(new ExecutionRetried($this->executionId(), $this->attempts + 1, $reason, $now));
    }

    /**
     * Move the execution into {@see ExecutionState::Review}.
     *
     * @param Clock $clock Time source.
     */
    public function toReview(Clock $clock): void
    {
        $now = $clock->now();
        ExecutionStateMachine::assert($this->state, ExecutionState::Review);
        $this->raise(new ExecutionSentToReview($this->executionId(), $now));
    }

    /**
     * Approve an execution under review, moving it into {@see ExecutionState::Approved}.
     *
     * @param string $approvedBy Identity performing the approval.
     * @param Clock  $clock      Time source.
     */
    public function approve(string $approvedBy, Clock $clock): void
    {
        Assert::notEmpty($approvedBy, 'Approval must record who approved it.');
        $now = $clock->now();
        ExecutionStateMachine::assert($this->state, ExecutionState::Approved);
        $this->raise(new ExecutionApproved($this->executionId(), $approvedBy, $now));
    }

    /**
     * Reject an execution under review, moving it into {@see ExecutionState::Rejected}.
     *
     * @param string $rejectedBy Identity performing the rejection.
     * @param string $reason     The human-readable reason for rejection.
     * @param Clock  $clock      Time source.
     */
    public function reject(string $rejectedBy, string $reason, Clock $clock): void
    {
        Assert::notEmpty($rejectedBy, 'Rejection must record who rejected it.');
        Assert::notEmpty($reason, 'Rejection must record a reason.');
        $now = $clock->now();
        ExecutionStateMachine::assert($this->state, ExecutionState::Rejected);
        $this->raise(new ExecutionRejected($this->executionId(), $rejectedBy, $reason, $now));
    }

    /**
     * Complete the execution successfully, moving it into terminal {@see ExecutionState::Completed}.
     *
     * @param Clock $clock Time source.
     */
    public function complete(Clock $clock): void
    {
        $now = $clock->now();
        ExecutionStateMachine::assert($this->state, ExecutionState::Completed);
        $this->raise(new ExecutionCompleted($this->executionId(), $now));
    }

    /**
     * Fail the execution, moving it into {@see ExecutionState::Failed}.
     *
     * @param string $reason The human-readable reason for the failure.
     * @param Clock  $clock  Time source.
     */
    public function fail(string $reason, Clock $clock): void
    {
        Assert::notEmpty($reason, 'A failure must record a reason.');
        $now = $clock->now();
        ExecutionStateMachine::assert($this->state, ExecutionState::Failed);
        $this->raise(new ExecutionFailed($this->executionId(), $reason, $now));
    }

    /**
     * Recover a failed execution, moving it into {@see ExecutionState::Recovered}.
     *
     * @param Clock $clock Time source.
     */
    public function recover(Clock $clock): void
    {
        $now = $clock->now();
        ExecutionStateMachine::assert($this->state, ExecutionState::Recovered);
        $this->raise(new ExecutionRecovered($this->executionId(), $now));
    }

    /**
     * Cancel the execution, moving it into terminal {@see ExecutionState::Cancelled}.
     *
     * @param string $cancelledBy Identity performing the cancellation.
     * @param Clock  $clock       Time source.
     */
    public function cancel(string $cancelledBy, Clock $clock): void
    {
        Assert::notEmpty($cancelledBy, 'Cancellation must record who performed it.');
        $now = $clock->now();
        ExecutionStateMachine::assert($this->state, ExecutionState::Cancelled);
        $this->raise(new ExecutionCancelled($this->executionId(), $cancelledBy, $now));
    }

    // -----------------------------------------------------------------------------------------
    // Event application (single source of truth for state derivation)
    // -----------------------------------------------------------------------------------------

    /**
     * Record a new event and immediately apply it to derive the new state.
     *
     * Buffers the event for later publication (via {@see AggregateRoot::pullDomainEvents()}) and then
     * mutates state through {@see self::applyEvent()}, guaranteeing that live mutation and replay
     * derive identical state.
     */
    private function raise(DomainEvent $event): void
    {
        $this->recordThat($event);
        $this->applyEvent($event);
    }

    /**
     * Apply one event to the aggregate's state without recording it.
     *
     * The single place where an event mutates state, shared by live mutation and {@see self::replay()}.
     */
    private function applyEvent(DomainEvent $event): void
    {
        match (true) {
            $event instanceof ExecutionStarted => $this->applyStarted($event),
            $event instanceof ExecutionPlanned => $this->transitionTo(
                ExecutionState::Planning,
                $event->occurredAt(),
                'Manager is planning the work.',
            ),
            $event instanceof WorkTaskAssigned => $this->applyAssigned($event),
            $event instanceof ExecutionStepStarted => $this->applyStepStarted($event),
            $event instanceof ExecutionStepCompleted => $this->applyStepCompleted($event),
            $event instanceof ExecutionRetried => $this->applyRetried($event),
            $event instanceof ExecutionSentToReview => $this->transitionTo(
                ExecutionState::Review,
                $event->occurredAt(),
                'Execution is held for review.',
            ),
            $event instanceof ExecutionApproved => $this->transitionTo(
                ExecutionState::Approved,
                $event->occurredAt(),
                sprintf('Approved by %s.', $event->approvedBy()),
            ),
            $event instanceof ExecutionRejected => $this->transitionTo(
                ExecutionState::Rejected,
                $event->occurredAt(),
                sprintf('Rejected by %s: %s', $event->rejectedBy(), $event->reason()),
            ),
            $event instanceof ExecutionCompleted => $this->transitionTo(
                ExecutionState::Completed,
                $event->occurredAt(),
                'Execution completed successfully.',
            ),
            $event instanceof ExecutionFailed => $this->transitionTo(
                ExecutionState::Failed,
                $event->occurredAt(),
                sprintf('Execution failed: %s', $event->reason()),
            ),
            $event instanceof ExecutionRecovered => $this->transitionTo(
                ExecutionState::Recovered,
                $event->occurredAt(),
                'Execution recovered from the event store.',
            ),
            $event instanceof ExecutionCancelled => $this->transitionTo(
                ExecutionState::Cancelled,
                $event->occurredAt(),
                sprintf('Cancelled by %s.', $event->cancelledBy()),
            ),
            default => null,
        };
    }

    /**
     * Apply the birth event: fix identity, metadata, and seed the timeline.
     */
    private function applyStarted(ExecutionStarted $event): void
    {
        $this->metadata = $event->metadata();
        $this->retryPolicy = $event->retryPolicy();
        $this->timeoutPolicy = $event->timeoutPolicy();
        $this->state = ExecutionState::Pending;
        $this->timeline = $this->timeline->append(
            ExecutionState::Pending,
            $event->occurredAt(),
            'Execution created and pending.',
        );
        $this->touch($event->occurredAt());
    }

    /**
     * Apply the assignment event: rebuild the step list and transition to Assigned.
     */
    private function applyAssigned(WorkTaskAssigned $event): void
    {
        $steps = [];
        foreach ($event->steps() as $descriptor) {
            $step = ExecutionStep::assign(
                ExecutionStepId::fromString($descriptor['stepId']),
                $descriptor['name'],
                $descriptor['workerRef'],
            );
            $steps[$descriptor['stepId']] = $step;
        }
        $this->steps = $steps;
        $this->transitionTo(ExecutionState::Assigned, $event->occurredAt(), 'Work steps assigned.');
    }

    /**
     * Apply a step-started event: mark the step running and take the execution into Running.
     *
     * The execution-level Running transition is derived here (it has no standalone event); it is
     * appended to the timeline only on the first entry into Running from a non-Running state, so
     * beginning several steps does not spam the timeline.
     */
    private function applyStepStarted(ExecutionStepStarted $event): void
    {
        $step = $this->steps[$event->stepId()->toString()] ?? null;
        if ($step instanceof ExecutionStep) {
            $step->begin($event->occurredAt());
        }

        if ($this->state !== ExecutionState::Running) {
            $this->transitionTo(ExecutionState::Running, $event->occurredAt(), 'Execution is running.');

            return;
        }

        $this->touch($event->occurredAt());
    }

    /**
     * Apply a step-completed event: attach the result, fold cost/performance, and derive Waiting.
     *
     * If pending steps remain after this one completes, the execution enters {@see ExecutionState::Waiting}
     * (awaiting the remaining work); otherwise it stays Running. This is the sole place the Waiting
     * state is derived, keeping it event-sourced.
     */
    private function applyStepCompleted(ExecutionStepCompleted $event): void
    {
        $step = $this->steps[$event->stepId()->toString()] ?? null;
        if ($step instanceof ExecutionStep) {
            $step->complete($event->result(), $event->occurredAt());
            $this->performance = $this->performance->recordStep(
                $step->durationMs() ?? $event->result()->executionTimeMs(),
            );
        } else {
            $this->performance = $this->performance->recordStep($event->result()->executionTimeMs());
        }

        $result = $event->result();
        $provider = $result->automationSelected();
        $breakdown = $provider !== null ? [$provider => $result->executionCostMicros()] : [];
        $this->cost = $this->cost->add(
            $this->tokensFrom($result),
            $result->executionCostMicros(),
            $breakdown,
        );

        if (
            $this->state === ExecutionState::Running
            && $this->firstPendingStep() !== null
            && ExecutionStateMachine::canTransition($this->state, ExecutionState::Waiting)
        ) {
            $this->transitionTo(
                ExecutionState::Waiting,
                $event->occurredAt(),
                'Execution is waiting for remaining steps.',
            );

            return;
        }

        $this->touch($event->occurredAt());
    }

    /**
     * Apply a retry event: bump attempts and the retry counter and enter Retrying.
     */
    private function applyRetried(ExecutionRetried $event): void
    {
        $this->attempts = $event->attempt();
        $this->performance = $this->performance->recordRetry();
        foreach ($this->steps as $step) {
            if (
                $step->state() === ExecutionStepState::Failed
                || $step->state() === ExecutionStepState::Running
            ) {
                $step->prepareRetry();
            }
        }
        $this->transitionTo(
            ExecutionState::Retrying,
            $event->occurredAt(),
            sprintf('Retry attempt %d: %s', $event->attempt(), $event->reason()),
        );
    }

    /**
     * Derive the token count a result reports under its resource map, defaulting to zero.
     */
    private function tokensFrom(WorkerResult $result): int
    {
        $tokens = $result->resourcesUsed()['tokens'] ?? 0;

        return is_int($tokens) && $tokens >= 0 ? $tokens : 0;
    }

    /**
     * Set the state and append a timeline entry, advancing the updated timestamp.
     */
    private function transitionTo(ExecutionState $state, DateTimeImmutable $at, string $note): void
    {
        $this->state = $state;
        $this->timeline = $this->timeline->append($state, $at, $note);
        $this->touch($at);
    }

    /**
     * The first step still awaiting execution (in insertion order), or null when none remain.
     */
    private function firstPendingStep(): ?ExecutionStep
    {
        foreach ($this->steps as $step) {
            if ($step->state() === ExecutionStepState::Pending) {
                return $step;
            }
        }

        return null;
    }

    /**
     * Assert a step id belongs to this execution.
     *
     * @throws UnknownStepException When the step is not present.
     */
    private function requireStep(ExecutionStepId $stepId): void
    {
        if (!array_key_exists($stepId->toString(), $this->steps)) {
            throw UnknownStepException::forId($stepId->toString(), $this->executionId()->toString());
        }
    }

    /**
     * Advance the updated timestamp and the optimistic-concurrency version after a mutation.
     */
    private function touch(DateTimeImmutable $now): void
    {
        $this->updatedAt = $now;
        ++$this->version;
    }

    // -----------------------------------------------------------------------------------------
    // Accessors
    // -----------------------------------------------------------------------------------------

    /**
     * The execution's identity, narrowed to {@see ExecutionId}.
     */
    public function executionId(): ExecutionId
    {
        $id = $this->id();
        assert($id instanceof ExecutionId);

        return $id;
    }

    /**
     * The descriptive context.
     */
    public function metadata(): ExecutionMetadata
    {
        return $this->metadata;
    }

    /**
     * The current lifecycle state.
     */
    public function state(): ExecutionState
    {
        return $this->state;
    }

    /**
     * The assigned steps, in insertion order.
     *
     * @return list<ExecutionStep>
     */
    public function steps(): array
    {
        return array_values($this->steps);
    }

    /**
     * A single step by id, or null when absent.
     */
    public function step(ExecutionStepId $stepId): ?ExecutionStep
    {
        return $this->steps[$stepId->toString()] ?? null;
    }

    /**
     * The human-readable path taken.
     */
    public function timeline(): ExecutionTimeline
    {
        return $this->timeline;
    }

    /**
     * The retry rules.
     */
    public function retryPolicy(): RetryPolicy
    {
        return $this->retryPolicy;
    }

    /**
     * The time budget.
     */
    public function timeoutPolicy(): TimeoutPolicy
    {
        return $this->timeoutPolicy;
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
     * How many attempts have been made (1-based).
     */
    public function attempts(): int
    {
        return $this->attempts;
    }

    /**
     * When the execution was created.
     */
    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * When the execution last changed.
     */
    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * The optimistic-concurrency version.
     */
    public function version(): int
    {
        return $this->version;
    }
}
