<?php

declare(strict_types=1);

namespace Nizam\Runtime\Execution\Domain\ValueObject;

use Nizam\Kernel\Domain\ValueObject;
use Nizam\Platform\Support\Assert;

/**
 * An immutable running summary of an execution's performance: elapsed time, steps, and retries.
 *
 * The execution folds a step's wall-clock time into this snapshot as the step completes and bumps
 * the step and retry counters as those events occur; each fold returns a new snapshot (the type is
 * immutable). Wall-clock and CPU time are in milliseconds; CPU time is optional because not every
 * runtime measures it. All values are non-negative.
 */
final class PerformanceSnapshot implements ValueObject
{
    /**
     * @param int      $wallMs     Total wall-clock time in milliseconds (>= 0).
     * @param int|null $cpuMs      Total CPU time in milliseconds, if measured (>= 0).
     * @param int      $stepCount  The number of steps executed (>= 0).
     * @param int      $retryCount The number of retries performed (>= 0).
     */
    public function __construct(
        private readonly int $wallMs,
        private readonly ?int $cpuMs,
        private readonly int $stepCount,
        private readonly int $retryCount,
    ) {
        Assert::that($wallMs >= 0, 'Wall-clock time must not be negative.');
        if ($cpuMs !== null) {
            Assert::that($cpuMs >= 0, 'CPU time must not be negative.');
        }
        Assert::that($stepCount >= 0, 'Step count must not be negative.');
        Assert::that($retryCount >= 0, 'Retry count must not be negative.');
    }

    /**
     * The zero snapshot: no time, no steps, no retries, no CPU measurement.
     */
    public static function zero(): self
    {
        return new self(0, null, 0, 0);
    }

    /**
     * Fold a completed step's wall-clock time in and increment the step counter.
     *
     * @param int $wallMs The step's wall-clock time in milliseconds (>= 0).
     */
    public function recordStep(int $wallMs): self
    {
        Assert::that($wallMs >= 0, 'Step wall-clock time must not be negative.');

        return new self(
            $this->wallMs + $wallMs,
            $this->cpuMs,
            $this->stepCount + 1,
            $this->retryCount,
        );
    }

    /**
     * Increment the retry counter, returning a new snapshot.
     */
    public function recordRetry(): self
    {
        return new self($this->wallMs, $this->cpuMs, $this->stepCount, $this->retryCount + 1);
    }

    /**
     * The total wall-clock time in milliseconds.
     */
    public function wallMs(): int
    {
        return $this->wallMs;
    }

    /**
     * The total CPU time in milliseconds, if measured.
     */
    public function cpuMs(): ?int
    {
        return $this->cpuMs;
    }

    /**
     * The number of steps executed.
     */
    public function stepCount(): int
    {
        return $this->stepCount;
    }

    /**
     * The number of retries performed.
     */
    public function retryCount(): int
    {
        return $this->retryCount;
    }

    /**
     * Structural equality across every attribute.
     */
    public function equals(ValueObject $other): bool
    {
        return $other instanceof self
            && $other->wallMs === $this->wallMs
            && $other->cpuMs === $this->cpuMs
            && $other->stepCount === $this->stepCount
            && $other->retryCount === $this->retryCount;
    }

    /**
     * A scalar-only representation suitable for JSON persistence and read models.
     *
     * @return array{wallMs: int, cpuMs: int|null, stepCount: int, retryCount: int}
     */
    public function toArray(): array
    {
        return [
            'wallMs' => $this->wallMs,
            'cpuMs' => $this->cpuMs,
            'stepCount' => $this->stepCount,
            'retryCount' => $this->retryCount,
        ];
    }
}
