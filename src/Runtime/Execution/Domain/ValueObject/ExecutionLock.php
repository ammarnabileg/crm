<?php

declare(strict_types=1);

namespace Nizam\Runtime\Execution\Domain\ValueObject;

use DateTimeImmutable;
use Nizam\Kernel\Domain\ValueObject;
use Nizam\Platform\Support\Assert;

/**
 * An immutable handle to a held execution lock: its key, the owner token, when it was taken, and TTL.
 *
 * The execution engine serializes all work on a single execution behind a lock obtained from the
 * {@see \Nizam\Runtime\Execution\Domain\Port\ExecutionLockManager}. This value object is the proof
 * of ownership returned by a successful acquire: the {@see self::ownerToken()} must be presented to
 * release or refresh, and {@see self::isExpiredAt()} lets a caller detect a lock that has outlived
 * its TTL (for example after a crash) so it may be reclaimed. Being a value object, a refresh yields
 * a new instance rather than mutating the old one.
 */
final class ExecutionLock implements ValueObject
{
    /**
     * @param string            $key        The lock key (typically the execution id).
     * @param string            $ownerToken The opaque token proving ownership; required to release/refresh.
     * @param DateTimeImmutable $acquiredAt When the lock was acquired.
     * @param int               $ttlMs      How long the lock remains valid from acquisition, in milliseconds (>= 1).
     */
    public function __construct(
        private readonly string $key,
        private readonly string $ownerToken,
        private readonly DateTimeImmutable $acquiredAt,
        private readonly int $ttlMs,
    ) {
        Assert::notEmpty($key, 'An execution lock must have a non-empty key.');
        Assert::notEmpty($ownerToken, 'An execution lock must have a non-empty owner token.');
        Assert::positive($ttlMs, 'An execution lock TTL must be a positive number of milliseconds.');
    }

    /**
     * The instant at which this lock expires (acquisition time plus TTL).
     */
    public function expiresAt(): DateTimeImmutable
    {
        return $this->acquiredAt->modify(sprintf('+%d milliseconds', $this->ttlMs));
    }

    /**
     * Whether the lock has expired as of the given instant.
     */
    public function isExpiredAt(DateTimeImmutable $now): bool
    {
        return $now >= $this->expiresAt();
    }

    /**
     * Produce a refreshed copy of the lock re-anchored to a new acquisition instant.
     *
     * @param DateTimeImmutable $now The new acquisition instant from which the TTL is measured.
     */
    public function refreshedAt(DateTimeImmutable $now): self
    {
        return new self($this->key, $this->ownerToken, $now, $this->ttlMs);
    }

    /**
     * Whether the supplied token owns this lock.
     */
    public function isOwnedBy(string $token): bool
    {
        return hash_equals($this->ownerToken, $token);
    }

    /**
     * The lock key (typically the execution id).
     */
    public function key(): string
    {
        return $this->key;
    }

    /**
     * The opaque token proving ownership.
     */
    public function ownerToken(): string
    {
        return $this->ownerToken;
    }

    /**
     * When the lock was acquired.
     */
    public function acquiredAt(): DateTimeImmutable
    {
        return $this->acquiredAt;
    }

    /**
     * How long the lock remains valid from acquisition, in milliseconds.
     */
    public function ttlMs(): int
    {
        return $this->ttlMs;
    }

    /**
     * Structural equality across every attribute.
     */
    public function equals(ValueObject $other): bool
    {
        return $other instanceof self
            && $other->key === $this->key
            && $other->ownerToken === $this->ownerToken
            && $other->acquiredAt->getTimestamp() === $this->acquiredAt->getTimestamp()
            && $other->ttlMs === $this->ttlMs;
    }
}
