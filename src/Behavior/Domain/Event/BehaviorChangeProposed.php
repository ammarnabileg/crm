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
 * Recorded when a change to a role's behavior profile is proposed for human review.
 *
 * A proposal captures recommended traits and their justification but changes nothing about
 * production behavior; it merely enters the review queue in
 * {@see \Nizam\Behavior\Domain\Enum\ProposalStatus::Pending}. Emitted when a
 * {@see \Nizam\Behavior\Domain\BehaviorChangeProposal} is proposed.
 */
final class BehaviorChangeProposed implements DomainEvent
{
    /**
     * @param ProposalId        $proposalId The proposal raised.
     * @param TenantId          $tenantId   The owning tenant.
     * @param RoleId            $roleId     The role the proposal targets.
     * @param BehaviorProfileId $profileId  The profile the proposal targets.
     * @param float             $confidence Confidence in the proposed change, in [0, 1].
     * @param string            $proposedBy Identity that raised the proposal.
     * @param DateTimeImmutable $occurredAt When the proposal was raised.
     */
    public function __construct(
        private readonly ProposalId $proposalId,
        private readonly TenantId $tenantId,
        private readonly RoleId $roleId,
        private readonly BehaviorProfileId $profileId,
        private readonly float $confidence,
        private readonly string $proposedBy,
        private readonly DateTimeImmutable $occurredAt,
    ) {
    }

    /**
     * The proposal raised.
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
     * The role the proposal targets.
     */
    public function roleId(): RoleId
    {
        return $this->roleId;
    }

    /**
     * The profile the proposal targets.
     */
    public function profileId(): BehaviorProfileId
    {
        return $this->profileId;
    }

    /**
     * The confidence in the proposed change, in [0, 1].
     */
    public function confidence(): float
    {
        return $this->confidence;
    }

    /**
     * The identity that raised the proposal.
     */
    public function proposedBy(): string
    {
        return $this->proposedBy;
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
        return 'behavior.change_proposed';
    }

    /**
     * {@inheritDoc}
     */
    public function aggregateId(): string
    {
        return $this->proposalId->toString();
    }
}
