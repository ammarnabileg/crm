<?php

declare(strict_types=1);

namespace Nizam\Runtime\Execution\Domain\Exception;

/**
 * Raised when an execution lock cannot be acquired, refreshed, or is used incorrectly.
 *
 * The execution engine serializes work on a single execution behind an
 * {@see \Nizam\Runtime\Execution\Domain\ValueObject\ExecutionLock} obtained from the
 * {@see \Nizam\Runtime\Execution\Domain\Port\ExecutionLockManager}. When another owner already holds
 * the lock, or a caller tries to release/refresh a lock it does not own, this exception is raised.
 * Carries error code `EXEC.LOCK`.
 */
final class ExecutionLockException extends ExecutionException
{
    /**
     * The stable error code for this violation.
     */
    public const string CODE = 'EXEC.LOCK';

    /**
     * Build the exception describing a failure to acquire a lock already held by another owner.
     *
     * @param string $key The contended lock key.
     */
    public static function notAcquired(string $key): self
    {
        return new self(
            self::CODE,
            sprintf('Could not acquire the execution lock "%s"; it is held by another owner.', $key),
        );
    }

    /**
     * Build the exception describing an operation attempted with a token that does not own the lock.
     *
     * @param string $key The lock key.
     */
    public static function notOwner(string $key): self
    {
        return new self(
            self::CODE,
            sprintf('The supplied token does not own the execution lock "%s".', $key),
        );
    }
}
