<?php

declare(strict_types=1);

namespace Nizam\Behavior\Domain\Event;

use DateTimeImmutable;
use Nizam\Behavior\Domain\BehaviorProfileId;
use Nizam\Behavior\Domain\RoleId;
use Nizam\Kernel\Domain\DomainEvent;
use Nizam\Kernel\Domain\TenantId;

/**
 * Recorded when a new behavior profile is drafted for a role.
 *
 * Marks the birth of a profile at version 1 in {@see \Nizam\Behavior\Domain\Enum\ProfileStatus::Draft};
 * the profile does not yet govern behavior. Emitted by {@see \Nizam\Behavior\Domain\BehaviorProfile::draft()}.
 */
final class BehaviorProfileDrafted implements DomainEvent
{
    /**
     * @param BehaviorProfileId $profileId  The drafted profile.
     * @param TenantId          $tenantId   The owning tenant.
     * @param RoleId            $roleId     The role the profile is bound to.
     * @param string            $draftedBy  Identity that drafted the profile.
     * @param DateTimeImmutable $occurredAt When the draft was created.
     */
    public function __construct(
        private readonly BehaviorProfileId $profileId,
        private readonly TenantId $tenantId,
        private readonly RoleId $roleId,
        private readonly string $draftedBy,
        private readonly DateTimeImmutable $occurredAt,
    ) {
    }

    /**
     * The drafted profile.
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
     * The role the profile is bound to.
     */
    public function roleId(): RoleId
    {
        return $this->roleId;
    }

    /**
     * The identity that drafted the profile.
     */
    public function draftedBy(): string
    {
        return $this->draftedBy;
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
        return 'behavior.profile_drafted';
    }

    /**
     * {@inheritDoc}
     */
    public function aggregateId(): string
    {
        return $this->profileId->toString();
    }
}
