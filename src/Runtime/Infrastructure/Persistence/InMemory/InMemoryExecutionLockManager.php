<?php

declare(strict_types=1);

namespace Nizam\Runtime\Infrastructure\Persistence\InMemory;

use Nizam\Kernel\Domain\Clock;
use Nizam\Platform\Support\Uuid;
use Nizam\Runtime\Execution\Domain\Exception\ExecutionLockException;
use Nizam\Runtime\Execution\Domain\Port\ExecutionLockManager;
use Nizam\Runtime\Execution\Domain\ValueObject\ExecutionLock;

/**
 * A real, single-process {@see ExecutionLockManager} for tests and safe defaults.
 *
 * It serializes work on an execution within one process using an in-memory table of held keys. An
 * acquire on a free (or expired) key mints an {@see ExecutionLock} with a fresh owner token; an acquire
 * on a live, held key returns null so the engine's idempotency/serialization path is exercised exactly
 * as in production. Release and refresh verify ownership by token, raising
 * {@see ExecutionLockException} when the presented token does not own the key. It is a working lock, not
 * a stub — the durable, multi-process equivalent is
 * {@see \Nizam\Runtime\Infrastructure\Persistence\Pdo\PdoExecutionLockManager}.
 */
final class InMemoryExecutionLockManager implements ExecutionLockManager
{
    /** @var array<string, ExecutionLock> */
    private array $held = [];

    /**
     * @param Clock $clock The time source used to stamp acquisition and detect expiry.
     */
    public function __construct(
        private readonly Clock $clock,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function acquire(string $key, int $ttlMs): ?ExecutionLock
    {
        $now = $this->clock->now();
        $existing = $this->held[$key] ?? null;
        if ($existing !== null && !$existing->isExpiredAt($now)) {
            return null;
        }

        $lock = new ExecutionLock($key, Uuid::v7(), $now, $ttlMs);
        $this->held[$key] = $lock;

        return $lock;
    }

    /**
     * {@inheritDoc}
     */
    public function refresh(ExecutionLock $lock): ExecutionLock
    {
        $this->assertOwned($lock);

        $refreshed = $lock->refreshedAt($this->clock->now());
        $this->held[$lock->key()] = $refreshed;

        return $refreshed;
    }

    /**
     * {@inheritDoc}
     */
    public function release(ExecutionLock $lock): void
    {
        $this->assertOwned($lock);

        unset($this->held[$lock->key()]);
    }

    /**
     * Assert the given lock is the one currently held for its key.
     *
     * @throws ExecutionLockException When no matching, owned lock is held.
     */
    private function assertOwned(ExecutionLock $lock): void
    {
        $held = $this->held[$lock->key()] ?? null;
        if ($held === null || !$held->isOwnedBy($lock->ownerToken())) {
            throw ExecutionLockException::notOwner($lock->key());
        }
    }
}
