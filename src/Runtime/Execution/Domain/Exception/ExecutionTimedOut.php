<?php

declare(strict_types=1);

namespace Nizam\Runtime\Execution\Domain\Exception;

/**
 * Raised when an execution or one of its steps exceeds its allotted time budget.
 *
 * The {@see \Nizam\Runtime\Execution\Domain\ValueObject\TimeoutPolicy} caps both the whole-execution
 * wall-clock duration and each step's duration; the application-layer timeout manager measures
 * elapsed time against those caps and raises this exception when either is exceeded. Carries error
 * code `EXEC.TIMED_OUT`.
 */
final class ExecutionTimedOut extends ExecutionException
{
    /**
     * The stable error code for this violation.
     */
    public const string CODE = 'EXEC.TIMED_OUT';

    /**
     * Build the exception describing a breach of the whole-execution wall-clock budget.
     *
     * @param int $elapsedMs The elapsed wall-clock time in milliseconds.
     * @param int $limitMs   The permitted wall-clock budget in milliseconds.
     */
    public static function wallClock(int $elapsedMs, int $limitMs): self
    {
        return new self(
            self::CODE,
            sprintf(
                'Execution exceeded its wall-clock budget: %d ms elapsed of %d ms allowed.',
                $elapsedMs,
                $limitMs,
            ),
        );
    }

    /**
     * Build the exception describing a breach of a single step's time budget.
     *
     * @param string $stepId    The identity of the step that timed out.
     * @param int    $elapsedMs The step's elapsed time in milliseconds.
     * @param int    $limitMs   The permitted per-step budget in milliseconds.
     */
    public static function step(string $stepId, int $elapsedMs, int $limitMs): self
    {
        return new self(
            self::CODE,
            sprintf(
                'Step "%s" exceeded its budget: %d ms elapsed of %d ms allowed.',
                $stepId,
                $elapsedMs,
                $limitMs,
            ),
        );
    }
}
