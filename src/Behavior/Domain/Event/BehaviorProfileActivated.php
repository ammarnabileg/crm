<?php

declare(strict_types=1);

namespace Nizam\Behavior\Domain\Event;

use DateTimeImmutable;
use Nizam\Behavior\Domain\BehaviorProfileId;
use Nizam\Behavior\Domain\RoleId;
use Nizam\Kernel\Domain\DomainEvent;
use Nizam\Kernel\Domain\TenantId;

/**
 * Recorded when a drafted behavior profile is activated and begins governing its role.
 *
 * Marks the transition from {@see \Nizam\Behavior\Domain\Enum\ProfileStatus::Draft} to
 * {@see \Nizam\Behavior\Domain\Enum\ProfileStatus::Active}. Emitted by
 * {@see \Nizam\Behavior\Domain\BehaviorProfile::activate()}.
 */
final class BehaviorProfileActivated implements DomainEvent
{
    /**
     * @param BehaviorProfileId $profileId   The activated profile.
     * @param TenantId          $tenantId    The owning tenant.
     * @param RoleId            $roleId      The role the profile governs.
     * @param int               $version     The version that became active.
     * @param string            $approvedBy  Identity that activated the profile.
     * @param DateTimeImmutable $occurredAt  When activation occurred.
     */
    public function __construct(
        private readonly BehaviorProfileId $profileId,
        private readonly TenantId $tenantId,
        private readonly RoleId $roleId,
        private readonly int $version,
        private readonly string $approvedBy,
        private readonly DateTimeImmutable $occurredAt,
    ) {
    }

    /**
     * The activated profile.
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
     * The version that became active.
     */
    public function version(): int
    {
        return $this->version;
    }

    /**
     * The identity that activated the profile.
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
        return 'behavior.profile_activated';
    }

    /**
     * {@inheritDoc}
     */
    public function aggregateId(): string
    {
        return $this->profileId->toString();
    }
}
