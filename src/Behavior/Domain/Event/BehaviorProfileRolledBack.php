<?php

declare(strict_types=1);

namespace Nizam\Behavior\Domain\Event;

use DateTimeImmutable;
use Nizam\Behavior\Domain\BehaviorProfileId;
use Nizam\Behavior\Domain\RoleId;
use Nizam\Kernel\Domain\DomainEvent;
use Nizam\Kernel\Domain\TenantId;

/**
 * Recorded when a behavior profile is rolled back to the traits of an earlier version.
 *
 * A rollback is itself append-only and reversible: it restores a prior revision's traits as a new,
 * higher version rather than deleting history. This event names both the restored source version and
 * the new version it produced. Emitted by {@see \Nizam\Behavior\Domain\BehaviorProfile::rollbackTo()}.
 */
final class BehaviorProfileRolledBack implements DomainEvent
{
    /**
     * @param BehaviorProfileId $profileId       The rolled-back profile.
     * @param TenantId          $tenantId        The owning tenant.
     * @param RoleId            $roleId          The role the profile governs.
     * @param int               $restoredVersion The earlier version whose traits were restored.
     * @param int               $newVersion      The new version produced by the rollback.
     * @param string            $approvedBy      Identity that approved the rollback.
     * @param DateTimeImmutable $occurredAt      When the rollback occurred.
     */
    public function __construct(
        private readonly BehaviorProfileId $profileId,
        private readonly TenantId $tenantId,
        private readonly RoleId $roleId,
        private readonly int $restoredVersion,
        private readonly int $newVersion,
        private readonly string $approvedBy,
        private readonly DateTimeImmutable $occurredAt,
    ) {
    }

    /**
     * The rolled-back profile.
     */
    public function profileId(): BehaviorProfileId
    {
        return $this->profileId;
    }

    /**
     * The owning tenant.
     */
    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    /**
     * The role the profile governs.
     */
    public function roleId(): RoleId
    {
        return $this->roleId;
    }

    /**
     * The earlier version whose traits were restored.
     */
    public function restoredVersion(): int
    {
        return $this->restoredVersion;
    }

    /**
     * The new version produced by the rollback.
     */
    public function newVersion(): int
    {
        return $this->newVersion;
    }

    /**
     * The identity that approved the rollback.
     */
    public function approvedBy(): string
    {
        return $this->approvedBy;
    }

    /**
     * {@inheritDoc}
     */
    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    /**
     * {@inheritDoc}
     */
    public function eventName(): string
    {
        return 'behavior.profile_rolled_back';
    }

    /**
     * {@inheritDoc}
     */
    public function aggregateId(): string
    {
        return $this->profileId->toString();
    }
}
