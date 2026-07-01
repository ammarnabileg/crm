<?php

declare(strict_types=1);

namespace Nizam\Behavior\Domain\Event;

use DateTimeImmutable;
use Nizam\Behavior\Domain\BehaviorProfileId;
use Nizam\Behavior\Domain\RoleId;
use Nizam\Kernel\Domain\DomainEvent;
use Nizam\Kernel\Domain\TenantId;

/**
 * Recorded when an approved change appends a new version to a behavior profile.
 *
 * Every applied trait change increments the profile's current version and appends an immutable
 * revision; this event announces that new version and the traits diff it carried. Emitted by
 * {@see \Nizam\Behavior\Domain\BehaviorProfile::applyApprovedChange()}.
 */
final class BehaviorProfileVersionActivated implements DomainEvent
{
    /**
     * @param BehaviorProfileId                            $profileId  The changed profile.
     * @param TenantId                                     $tenantId   The owning tenant.
     * @param RoleId                                       $roleId     The role the profile governs.
     * @param int                                          $version    The newly activated version.
     * @param array<string, array{from: string, to: string}> $traitsDiff The per-trait diff applied.
     * @param string                                       $approvedBy Identity that approved the change.
     * @param DateTimeImmutable                            $occurredAt When the version was activated.
     */
    public function __construct(
        private readonly BehaviorProfileId $profileId,
        private readonly TenantId $tenantId,
        private readonly RoleId $roleId,
        private readonly int $version,
        private readonly array $traitsDiff,
        private readonly string $approvedBy,
        private readonly DateTimeImmutable $occurredAt,
    ) {
    }

    /**
     * The changed profile.
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
     * The newly activated version.
     */
    public function version(): int
    {
        return $this->version;
    }

    /**
     * The per-trait diff applied by the change.
     *
     * @return array<string, array{from: string, to: string}>
     */
    public function traitsDiff(): array
    {
        return $this->traitsDiff;
    }

    /**
     * The identity that approved the change.
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
        return 'behavior.profile_version_activated';
    }

    /**
     * {@inheritDoc}
     */
    public function aggregateId(): string
    {
        return $this->profileId->toString();
    }
}
