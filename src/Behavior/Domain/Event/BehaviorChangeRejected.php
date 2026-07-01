<?php

declare(strict_types=1);

namespace Nizam\Behavior\Domain\Event;

use DateTimeImmutable;
use Nizam\Behavior\Domain\BehaviorProfileId;
use Nizam\Behavior\Domain\ProposalId;
use Nizam\Behavior\Domain\RoleId;
use Nizam\Kernel\Domain\DomainEvent;
use Nizam\Kernel\Domain\TenantId;

/**
 * Recorded when a pending behavior change proposal is rejected by a human.
 *
 * Rejection marks the proposal {@see \Nizam\Behavior\Domain\Enum\ProposalStatus::Rejected}; it will
 * never be applied, and the reason is retained for audit. Emitted by
 * {@see \Nizam\Behavior\Domain\BehaviorChangeProposal::reject()}.
 */
final class BehaviorChangeRejected implements DomainEvent
{
    /**
     * @param ProposalId        $proposalId The rejected proposal.
     * @param TenantId          $tenantId   The owning tenant.
     * @param RoleId            $roleId     The role the proposal targeted.
     * @param BehaviorProfileId $profileId  The profile the proposal targeted.
     * @param string            $reason     Why the proposal was rejected.
     * @param string            $rejectedBy Identity that rejected the proposal.
     * @param DateTimeImmutable $occurredAt When rejection occurred.
     */
    public function __construct(
        private readonly ProposalId $proposalId,
        private readonly TenantId $tenantId,
        private readonly RoleId $roleId,
        private readonly BehaviorProfileId $profileId,
        private readonly string $reason,
        private readonly string $rejectedBy,
        private readonly DateTimeImmutable $occurredAt,
    ) {
    }

    /**
     * The rejected proposal.
     */
    public function proposalId(): ProposalId
    {
        return $this->proposalId;
    }

    /**
     * The owning tenant.
     */
    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    /**
     * The role the proposal targeted.
     */
    public function roleId(): RoleId
    {
        return $this->roleId;
    }

    /**
     * The profile the proposal targeted.
     */
    public function profileId(): BehaviorProfileId
    {
        return $this->profileId;
    }

    /**
     * The reason the proposal was rejected.
     */
    public function reason(): string
    {
        return $this->reason;
    }

    /**
     * The identity that rejected the proposal.
     */
    public function rejectedBy(): string
    {
        return $this->rejectedBy;
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
        return 'behavior.change_rejected';
    }

    /**
     * {@inheritDoc}
     */
    public function aggregateId(): string
    {
        return $this->proposalId->toString();
    }
}
