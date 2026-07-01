<?php

declare(strict_types=1);

namespace Nizam\Runtime\Execution\Domain;

use DateTimeImmutable;
use Nizam\Kernel\Domain\Entity;
use Nizam\Platform\Support\Assert;
use Nizam\Runtime\Execution\Domain\ValueObject\WorkerResult;

/**
 * One assigned unit of work within an {@see Execution}, typically dispatched to a worker plugin.
 *
 * A step tracks its own small lifecycle: it is created {@see ExecutionStepState::Pending} with a
 * worker reference, {@see self::begin()} marks it running and stamps its start, and
 * {@see self::complete()} attaches the returned {@see WorkerResult} and stamps its finish, moving it
 * to {@see ExecutionStepState::Completed} or {@see ExecutionStepState::Failed} depending on whether
 * the worker reported errors. The {@see self::attempt()} counter records how many times this step has
 * been (re)tried. An entity, it has identity ({@see ExecutionStepId}) and mutable state, but all
 * time comes from the caller so the domain performs no I/O.
 */
final class ExecutionStep extends Entity
{
    /**
     * @param ExecutionStepId        $id         The step's identity.
     * @param string                 $name       A human-readable name for the step.
     * @param ExecutionStepState     $state      The step's lifecycle state.
     * @param string                 $workerRef  The name of the worker plugin the step is assigned to.
     * @param int                    $attempt    Which attempt this is (1-based).
     * @param DateTimeImmutable|null $startedAt  When the step began, if it has.
     * @param DateTimeImmutable|null $finishedAt When the step finished, if it has.
     * @param WorkerResult|null      $result     The worker's result, once completed.
     */
    private function __construct(
        ExecutionStepId $id,
        private readonly string $name,
        private ExecutionStepState $state,
        private readonly string $workerRef,
        private int $attempt,
        private ?DateTimeImmutable $startedAt,
        private ?DateTimeImmutable $finishedAt,
        private ?WorkerResult $result,
    ) {
        parent::__construct($id);
    }

    /**
     * Assign a fresh, pending step to a worker.
     *
     * @param ExecutionStepId $id        The step identity.
     * @param string          $name      A human-readable name.
     * @param string          $workerRef The worker plugin the step is assigned to.
     */
    public static function assign(ExecutionStepId $id, string $name, string $workerRef): self
    {
        Assert::notEmpty($name, 'A step must have a non-empty name.');
        Assert::notEmpty($workerRef, 'A step must reference a worker plugin.');

        return new self(
            id: $id,
            name: $name,
            state: ExecutionStepState::Pending,
            workerRef: $workerRef,
            attempt: 1,
            startedAt: null,
            finishedAt: null,
            result: null,
        );
    }

    /**
     * Reconstitute a step from persisted state (no validation of history beyond structural).
     *
     * @param ExecutionStepId        $id         The step identity.
     * @param string                 $name       The step name.
     * @param ExecutionStepState     $state      The step state.
     * @param string                 $workerRef  The worker plugin reference.
     * @param int                    $attempt    The attempt counter (>= 1).
     * @param DateTimeImmutable|null $startedAt  When the step began, if it has.
     * @param DateTimeImmutable|null $finishedAt When the step finished, if it has.
     * @param WorkerResult|null      $result     The worker's result, if completed.
     */
    public static function reconstitute(
        ExecutionStepId $id,
        string $name,
        ExecutionStepState $state,
        string $workerRef,
        int $attempt,
        ?DateTimeImmutable $startedAt,
        ?DateTimeImmutable $finishedAt,
        ?WorkerResult $result,
    ): self {
        Assert::positive($attempt, 'A step attempt must be a positive integer.');

        return new self($id, $name, $state, $workerRef, $attempt, $startedAt, $finishedAt, $result);
    }

    /**
     * Mark the step as running and stamp its start time.
     *
     * @param DateTimeImmutable $now When the step began.
     */
    public function begin(DateTimeImmutable $now): void
    {
        $this->state = ExecutionStepState::Running;
        $this->startedAt = $now;
    }

    /**
     * Attach the worker's result and finish the step.
     *
     * The step becomes {@see ExecutionStepState::Failed} when the result reports errors, otherwise
     * {@see ExecutionStepState::Completed}.
     *
     * @param WorkerResult      $result The worker's structured output.
     * @param DateTimeImmutable $now    When the step finished.
     */
    public function complete(WorkerResult $result, DateTimeImmutable $now): void
    {
        $this->result = $result;
        $this->finishedAt = $now;
        $this->state = $result->hasErrors()
            ? ExecutionStepState::Failed
            : ExecutionStepState::Completed;
    }

    /**
     * Mark the step as failed without a worker result (e.g. dispatch could not occur).
     *
     * @param DateTimeImmutable $now When the failure was recorded.
     */
    public function fail(DateTimeImmutable $now): void
    {
        $this->state = ExecutionStepState::Failed;
        $this->finishedAt = $now;
    }

    /**
     * Reset the step to pending for another attempt, bumping the attempt counter.
     */
    public function prepareRetry(): void
    {
        $this->state = ExecutionStepState::Pending;
        $this->startedAt = null;
        $this->finishedAt = null;
        $this->result = null;
        ++$this->attempt;
    }

    /**
     * The step's identity, narrowed to {@see ExecutionStepId}.
     */
    public function stepId(): ExecutionStepId
    {
        $id = $this->id();
        assert($id instanceof ExecutionStepId);

        return $id;
    }

    /**
     * The human-readable name.
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * The step's lifecycle state.
     */
    public function state(): ExecutionStepState
    {
        return $this->state;
    }

    /**
     * The worker plugin the step is assigned to.
     */
    public function workerRef(): string
    {
        return $this->workerRef;
    }

    /**
     * Which attempt this is (1-based).
     */
    public function attempt(): int
    {
        return $this->attempt;
    }

    /**
     * When the step began, if it has.
     */
    public function startedAt(): ?DateTimeImmutable
    {
        return $this->startedAt;
    }

    /**
     * When the step finished, if it has.
     */
    public function finishedAt(): ?DateTimeImmutable
    {
        return $this->finishedAt;
    }

    /**
     * The worker's result, once completed.
     */
    public function result(): ?WorkerResult
    {
        return $this->result;
    }

    /**
     * The step's wall-clock duration in milliseconds, or null when not yet finished.
     */
    public function durationMs(): ?int
    {
        if ($this->startedAt === null || $this->finishedAt === null) {
            return null;
        }

        $startUs = (int) $this->startedAt->format('Uu');
        $finishUs = (int) $this->finishedAt->format('Uu');

        return intdiv($finishUs - $startUs, 1000);
    }
}
