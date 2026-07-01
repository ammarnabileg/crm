<?php

declare(strict_types=1);

namespace Nizam\Runtime\Execution\Domain\Exception;

/**
 * Raised when an execution has failed as many times as its retry policy permits.
 *
 * The {@see \Nizam\Runtime\Execution\Domain\ValueObject\RetryPolicy} caps the number of attempts;
 * once that cap is reached, {@see \Nizam\Runtime\Execution\Domain\Execution::retry()} refuses to
 * schedule another attempt and raises this exception so the caller can fail the execution. Carries
 * error code `EXEC.RETRY_EXHAUSTED`.
 */
final class RetryExhausted extends ExecutionException
{
    /**
     * The stable error code for this violation.
     */
    public const string CODE = 'EXEC.RETRY_EXHAUSTED';

    /**
     * Build the exception describing the exhausted retry budget.
     *
     * @param int $attempts    The number of attempts already made.
     * @param int $maxAttempts The maximum attempts the policy allowed.
     */
    public static function forPolicy(int $attempts, int $maxAttempts): self
    {
        return new self(
            self::CODE,
            sprintf(
                'Retry budget exhausted after %d attempt(s); the policy allows at most %d.',
                $attempts,
                $maxAttempts,
            ),
        );
    }
}
