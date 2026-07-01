<?php

declare(strict_types=1);

namespace Nizam\Runtime\Execution\Application;

use DateTimeImmutable;
use Nizam\Kernel\Domain\Clock;
use Nizam\Runtime\Execution\Domain\Exception\ExecutionTimedOut;
use Nizam\Runtime\Execution\Domain\ValueObject\TimeoutPolicy;

/**
 * The application service that enforces an execution's wall-clock and per-step time budgets.
 *
 * A manager is anchored to the instant an execution (or a step) began — read once from the injected
 * {@see Clock} — and thereafter measures elapsed milliseconds by re-reading the clock and diffing
 * against the anchor. Because the anchor is captured once and only ever compared forward, elapsed time
 * is treated monotonically: a well-behaved production clock never moves backward, and a test clock can
 * advance deterministically. When the elapsed time exceeds a {@see TimeoutPolicy} cap, the guard
 * methods raise {@see ExecutionTimedOut}; the non-throwing predicates let callers check without
 * failing. The manager performs no I/O beyond reading the clock.
 */
final class TimeoutManager
{
    /**
     * @param TimeoutPolicy     $policy    The wall-clock and per-step budgets to enforce.
     * @param Clock             $clock     The monotonic-forward time source.
     * @param DateTimeImmutable $startedAt The instant the execution began (the wall-clock anchor).
     */
    private function __construct(
        private readonly TimeoutPolicy $policy,
        private readonly Clock $clock,
        private readonly DateTimeImmutable $startedAt,
    ) {
    }

    /**
     * Start a manager anchored to the clock's current instant.
     *
     * @param TimeoutPolicy $policy The budgets to enforce.
     * @param Clock         $clock  The monotonic-forward time source.
     */
    public static function start(TimeoutPolicy $policy, Clock $clock): self
    {
        return new self($policy, $clock, $clock->now());
    }

    /**
     * The elapsed wall-clock time since the anchor, in milliseconds, per the current clock reading.
     */
    public function elapsedMs(): int
    {
        return $this->diffMs($this->startedAt, $this->clock->now());
    }

    /**
     * Whether the whole-execution wall-clock budget has been exceeded as of now.
     */
    public function wallClockExceeded(): bool
    {
        return $this->policy->wallClockExceeded($this->elapsedMs());
    }

    /**
     * Guard the whole-execution wall-clock budget, raising when it has been exceeded.
     *
     * @throws ExecutionTimedOut When the elapsed wall-clock time exceeds the policy's wall-clock cap.
     */
    public function assertWithinWallClock(): void
    {
        $elapsed = $this->elapsedMs();
        if ($this->policy->wallClockExceeded($elapsed)) {
            throw ExecutionTimedOut::wallClock($elapsed, $this->policy->wallClockMs());
        }
    }

    /**
     * Whether a step that began at the given instant has exceeded the per-step budget as of now.
     *
     * @param DateTimeImmutable $stepStartedAt When the step began.
     */
    public function stepExceeded(DateTimeImmutable $stepStartedAt): bool
    {
        return $this->policy->stepExceeded($this->diffMs($stepStartedAt, $this->clock->now()));
    }

    /**
     * Guard a step's time budget, raising when the step has run longer than the per-step cap.
     *
     * @param string            $stepId        The identity of the step being guarded.
     * @param DateTimeImmutable $stepStartedAt When the step began.
     *
     * @throws ExecutionTimedOut When the step's elapsed time exceeds the policy's per-step cap.
     */
    public function assertStepWithin(string $stepId, DateTimeImmutable $stepStartedAt): void
    {
        $elapsed = $this->diffMs($stepStartedAt, $this->clock->now());
        if ($this->policy->stepExceeded($elapsed)) {
            throw ExecutionTimedOut::step($stepId, $elapsed, $this->policy->perStepMs());
        }
    }

    /**
     * The instant the execution began (the wall-clock anchor).
     */
    public function startedAt(): DateTimeImmutable
    {
        return $this->startedAt;
    }

    /**
     * The whole, forward-only difference between two instants, in milliseconds (floored at zero).
     */
    private function diffMs(DateTimeImmutable $from, DateTimeImmutable $to): int
    {
        $fromUs = (int) $from->format('Uu');
        $toUs = (int) $to->format('Uu');
        $deltaMs = intdiv($toUs - $fromUs, 1000);

        return $deltaMs > 0 ? $deltaMs : 0;
    }
}
