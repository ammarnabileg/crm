<?php

declare(strict_types=1);

namespace Nizam\Runtime\Execution\Domain\Exception;

use Nizam\Runtime\Execution\Domain\ExecutionState;

/**
 * Raised when a state change is attempted that the execution state machine forbids.
 *
 * The {@see \Nizam\Runtime\Execution\Domain\ExecutionStateMachine} defines the only legal moves
 * between {@see ExecutionState}s; any other move (including any move out of a terminal state) is a
 * violation. Carries error code `EXEC.ILLEGAL_TRANSITION`.
 */
final class IllegalExecutionTransition extends ExecutionException
{
    /**
     * The stable error code for this violation.
     */
    public const string CODE = 'EXEC.ILLEGAL_TRANSITION';

    /**
     * Build the exception describing the rejected transition.
     *
     * @param ExecutionState $from The state the execution was in.
     * @param ExecutionState $to   The state that was illegally requested.
     */
    public static function between(ExecutionState $from, ExecutionState $to): self
    {
        return new self(
            self::CODE,
            sprintf(
                'Cannot transition an execution from "%s" to "%s".',
                $from->value,
                $to->value,
            ),
        );
    }
}
