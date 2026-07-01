<?php

declare(strict_types=1);

namespace Nizam\Runtime\Execution\Domain\ValueObject;

use Nizam\Kernel\Domain\ValueObject;
use Nizam\Platform\Support\Assert;

/**
 * The immutable time budget for an execution: a whole-run wall-clock cap and a per-step cap.
 *
 * A pure value object consulted by the application-layer timeout manager, which measures elapsed
 * time (via a monotonic {@see \Nizam\Kernel\Domain\Clock}) against these caps and raises
 * {@see \Nizam\Runtime\Execution\Domain\Exception\ExecutionTimedOut} when either is exceeded. Both
 * budgets are in milliseconds and must be positive.
 */
final class TimeoutPolicy implements ValueObject
{
    /**
     * @param int $wallClockMs The whole-execution wall-clock budget in milliseconds (>= 1).
     * @param int $perStepMs   The per-step budget in milliseconds (>= 1).
     */
    public function __construct(
        private readonly int $wallClockMs,
        private readonly int $perStepMs,
    ) {
        Assert::positive($wallClockMs, 'The wall-clock timeout must be a positive number of milliseconds.');
        Assert::positive($perStepMs, 'The per-step timeout must be a positive number of milliseconds.');
    }

    /**
     * A sensible default budget: 30 s overall, 10 s per step.
     */
    public static function default(): self
    {
        return new self(30_000, 10_000);
    }

    /**
     * Whether the given elapsed wall-clock time has exceeded the whole-execution budget.
     *
     * @param int $elapsedMs The elapsed wall-clock time in milliseconds.
     */
    public function wallClockExceeded(int $elapsedMs): bool
    {
        return $elapsedMs > $this->wallClockMs;
    }

    /**
     * Whether the given elapsed step time has exceeded the per-step budget.
     *
     * @param int $elapsedMs The step's elapsed time in milliseconds.
     */
    public function stepExceeded(int $elapsedMs): bool
    {
        return $elapsedMs > $this->perStepMs;
    }

    /**
     * The whole-execution wall-clock budget in milliseconds.
     */
    public function wallClockMs(): int
    {
        return $this->wallClockMs;
    }

    /**
     * The per-step budget in milliseconds.
     */
    public function perStepMs(): int
    {
        return $this->perStepMs;
    }

    /**
     * Structural equality across both budgets.
     */
    public function equals(ValueObject $other): bool
    {
        return $other instanceof self
            && $other->wallClockMs === $this->wallClockMs
            && $other->perStepMs === $this->perStepMs;
    }

    /**
     * A scalar-only representation suitable for JSON persistence and read models.
     *
     * @return array{wallClockMs: int, perStepMs: int}
     */
    public function toArray(): array
    {
        return [
            'wallClockMs' => $this->wallClockMs,
            'perStepMs' => $this->perStepMs,
        ];
    }
}
