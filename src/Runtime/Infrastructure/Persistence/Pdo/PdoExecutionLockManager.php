<?php

declare(strict_types=1);

namespace Nizam\Runtime\Infrastructure\Persistence\Pdo;

use DateTimeImmutable;
use Nizam\Kernel\Domain\Clock;
use Nizam\Platform\Support\Uuid;
use Nizam\Runtime\Execution\Domain\Exception\ExecutionLockException;
use Nizam\Runtime\Execution\Domain\Port\ExecutionLockManager;
use Nizam\Runtime\Execution\Domain\ValueObject\ExecutionLock;
use PDO;

/**
 * A PDO-backed, multi-process {@see ExecutionLockManager} using an advisory lock row with a TTL.
 *
 * Mutual exclusion on an execution is modelled as a single row per lock key in `execution_locks`. An
 * acquire inserts the row when the key is free, or steals it when the current holder's TTL has elapsed
 * (a crash-safety valve: a holder that died without releasing cannot block the key forever); a live,
 * held key yields null so the engine's serialization/idempotency path runs exactly as in-memory. The
 * returned {@see ExecutionLock} carries an owner token that {@see self::refresh()} and
 * {@see self::release()} check against the stored row, so only the true owner may extend or free the
 * lock — a mismatch raises {@see ExecutionLockException}. Every write is guarded by a WHERE that
 * re-checks ownership/expiry, so concurrent acquirers cannot both win. This is a database advisory row,
 * not a distributed lock service; a Redis/true-distributed lock is a future phase.
 */
final class PdoExecutionLockManager implements ExecutionLockManager
{
    /**
     * @param PDO   $connection The database connection (SQLite or PostgreSQL).
     * @param Clock $clock      The time source used to stamp acquisition and detect expiry.
     */
    public function __construct(
        private readonly PDO $connection,
        private readonly Clock $clock,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function acquire(string $key, int $ttlMs): ?ExecutionLock
    {
        $now = $this->clock->now();
        $token = Uuid::v7();
        $expiresAt = $this->expiryOf($now, $ttlMs);

        if ($this->insert($key, $token, $now, $ttlMs, $expiresAt)) {
            return new ExecutionLock($key, $token, $now, $ttlMs);
        }

        // The row exists; steal it only if the current holder's TTL has elapsed.
        if ($this->steal($key, $token, $now, $ttlMs, $expiresAt)) {
            return new ExecutionLock($key, $token, $now, $ttlMs);
        }

        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function refresh(ExecutionLock $lock): ExecutionLock
    {
        $now = $this->clock->now();
        $refreshed = $lock->refreshedAt($now);

        $statement = $this->connection->prepare(
            'UPDATE execution_locks
                SET acquired_at = :acquired_at,
                    ttl_ms = :ttl_ms,
                    expires_at = :expires_at
              WHERE lock_key = :lock_key AND owner_token = :owner_token',
        );
        $statement->execute([
            'acquired_at' => $now->format(DateTimeImmutable::ATOM),
            'ttl_ms' => $refreshed->ttlMs(),
            'expires_at' => $this->expiryOf($now, $refreshed->ttlMs()),
            'lock_key' => $lock->key(),
            'owner_token' => $lock->ownerToken(),
        ]);

        if ($statement->rowCount() === 0) {
            throw ExecutionLockException::notOwner($lock->key());
        }

        return $refreshed;
    }

    /**
     * {@inheritDoc}
     */
    public function release(ExecutionLock $lock): void
    {
        $statement = $this->connection->prepare(
            'DELETE FROM execution_locks
              WHERE lock_key = :lock_key AND owner_token = :owner_token',
        );
        $statement->execute([
            'lock_key' => $lock->key(),
            'owner_token' => $lock->ownerToken(),
        ]);

        if ($statement->rowCount() === 0) {
            throw ExecutionLockException::notOwner($lock->key());
        }
    }

    /**
     * Insert a fresh lock row, returning whether the insert succeeded (false on a key collision).
     */
    private function insert(
        string $key,
        string $token,
        DateTimeImmutable $now,
        int $ttlMs,
        string $expiresAt,
    ): bool {
        try {
            $statement = $this->connection->prepare(
                'INSERT INTO execution_locks
                    (lock_key, owner_token, acquired_at, ttl_ms, expires_at)
                 VALUES
                    (:lock_key, :owner_token, :acquired_at, :ttl_ms, :expires_at)',
            );

            return $statement->execute([
                'lock_key' => $key,
                'owner_token' => $token,
                'acquired_at' => $now->format(DateTimeImmutable::ATOM),
                'ttl_ms' => $ttlMs,
                'expires_at' => $expiresAt,
            ]);
        } catch (\PDOException) {
            // A unique-key violation means the key is already held; fall through to the steal path.
            return false;
        }
    }

    /**
     * Steal an existing lock row whose TTL has elapsed, returning whether the takeover succeeded.
     */
    private function steal(
        string $key,
        string $token,
        DateTimeImmutable $now,
        int $ttlMs,
        string $expiresAt,
    ): bool {
        $statement = $this->connection->prepare(
            'UPDATE execution_locks
                SET owner_token = :owner_token,
                    acquired_at = :acquired_at,
                    ttl_ms = :ttl_ms,
                    expires_at = :expires_at
              WHERE lock_key = :lock_key AND expires_at <= :now',
        );
        $statement->execute([
            'owner_token' => $token,
            'acquired_at' => $now->format(DateTimeImmutable::ATOM),
            'ttl_ms' => $ttlMs,
            'expires_at' => $expiresAt,
            'lock_key' => $key,
            'now' => $now->format(DateTimeImmutable::ATOM),
        ]);

        return $statement->rowCount() > 0;
    }

    /**
     * Compute the ISO-8601 expiry instant for a lock acquired now with the given TTL.
     */
    private function expiryOf(DateTimeImmutable $now, int $ttlMs): string
    {
        return $now->modify(sprintf('+%d milliseconds', $ttlMs))->format(DateTimeImmutable::ATOM);
    }
}
