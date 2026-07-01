<?php

declare(strict_types=1);

namespace Nizam\Behavior\Domain\Event;

use DateTimeImmutable;
use Nizam\Behavior\Domain\BehaviorProfileId;
use Nizam\Behavior\Domain\RoleId;
use Nizam\Kernel\Domain\DomainEvent;
use Nizam\Kernel\Domain\TenantId;

/**
 * Recorded when a behavior profile is archived and retired from use.
 *
 * Marks the transition to {@see \Nizam\Behavior\Domain\Enum\ProfileStatus::Archived}; the profile
 * no longer governs behavior but its history is retained. Emitted by
 * {@see \Nizam\Behavior\Domain\BehaviorProfile::archive()}.
 */
final class BehaviorProfileArchived implements DomainEvent
{
    /**
     * @param BehaviorProfileId $profileId  The archived profile.
     * @param TenantId          $tenantId   The owning tenant.
     * @param RoleId            $roleId     The role the profile governed.
     * @param string            $archivedBy Identity that archived the profile.
     * @param DateTimeImmutable $occurredAt When archival occurred.
     */
    public function __construct(
        private readonly BehaviorProfileId $profileId,
        private readonly TenantId $tenantId,
        private readonly RoleId $roleId,
        private readonly string $archivedBy,
        private readonly DateTimeImmutable $occurredAt,
    ) {
    }

    /**
     * The archived profile.
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
     * The role the profile governed.
     */
    public function roleId(): RoleId
    {
        return $this->roleId;
    }

    /**
     * The identity that archived the profile.
     */
    public function archivedBy(): string
    {
        return $this->archivedBy;
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
        return 'behavior.profile_archived';
    }

    /**
     * {@inheritDoc}
     */
    public function aggregateId(): string
    {
        return $this->profileId->toString();
    }
}
