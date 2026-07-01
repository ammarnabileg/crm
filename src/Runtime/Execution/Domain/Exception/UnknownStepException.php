<?php

declare(strict_types=1);

namespace Nizam\Runtime\Execution\Domain\Exception;

/**
 * Raised when an operation references an execution step that the execution does not own.
 *
 * Beginning, awaiting, or completing a step requires an {@see \Nizam\Runtime\Execution\Domain\ExecutionStepId}
 * that was previously assigned to the execution; referencing any other step is a violation. Carries
 * error code `EXEC.UNKNOWN_STEP`.
 */
final class UnknownStepException extends ExecutionException
{
    /**
     * The stable error code for this violation.
     */
    public const string CODE = 'EXEC.UNKNOWN_STEP';

    /**
     * Build the exception for a missing step within an execution.
     *
     * @param string $stepId      The step id that was not found.
     * @param string $executionId The execution that was searched.
     */
    public static function forId(string $stepId, string $executionId): self
    {
        return new self(
            self::CODE,
            sprintf('Execution "%s" has no step "%s".', $executionId, $stepId),
        );
    }
}
