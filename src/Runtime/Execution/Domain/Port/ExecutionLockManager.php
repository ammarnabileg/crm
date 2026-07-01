<?php

declare(strict_types=1);

namespace Nizam\Runtime\Execution\Domain\Port;

use Nizam\Runtime\Execution\Domain\ValueObject\ExecutionLock;

/**
 * The mutual-exclusion port that serializes work on a single execution.
 *
 * The domain declares this port; infrastructure adapters implement it (in-memory for one process, a
 * DB advisory-row adapter for multi-process). The execution engine acquires a lock keyed by the
 * execution id before mutating it, so two concurrent runs of the same execution cannot interleave.
 * A successful acquire returns an {@see ExecutionLock} carrying the owner token that must be
 * presented to {@see self::refresh()} or {@see self::release()}; a contended acquire returns null so
 * the caller can back off or treat the run as already in progress.
 */
interface ExecutionLockManager
{
    /**
     * Attempt to acquire the lock for a key, returning the held lock or null when contended.
     *
     * @param string $key   The lock key (typically the execution id).
     * @param int    $ttlMs How long the lock should remain valid, in milliseconds (>= 1).
     */
    public function acquire(string $key, int $ttlMs): ?ExecutionLock;

    /**
     * Extend a held lock's TTL, returning the refreshed lock.
     *
     * @throws \Nizam\Runtime\Execution\Domain\Exception\ExecutionLockException When the token does not own the lock.
     */
    public function refresh(ExecutionLock $lock): ExecutionLock;

    /**
     * Release a held lock. Releasing a lock not owned by the token is a no-op or raises, per adapter.
     */
    public function release(ExecutionLock $lock): void;
}
