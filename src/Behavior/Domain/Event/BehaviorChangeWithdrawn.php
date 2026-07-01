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
 * Recorded when a proposer withdraws a pending behavior change proposal before a decision.
 *
 * Withdrawal marks the proposal {@see \Nizam\Behavior\Domain\Enum\ProposalStatus::Withdrawn}; it is
 * terminal and the proposal will never be applied. Emitted by
 * {@see \Nizam\Behavior\Domain\BehaviorChangeProposal::withdraw()}.
 */
final class BehaviorChangeWithdrawn implements DomainEvent
{
    /**
     * @param ProposalId        $proposalId  The withdrawn proposal.
     * @param TenantId          $tenantId    The owning tenant.
     * @param RoleId            $roleId      The role the proposal targeted.
     * @param BehaviorProfileId $profileId   The profile the proposal targeted.
     * @param string            $withdrawnBy Identity that withdrew the proposal.
     * @param DateTimeImmutable $occurredAt  When withdrawal occurred.
     */
    public function __construct(
        private readonly ProposalId $proposalId,
        private readonly TenantId $tenantId,
        private readonly RoleId $roleId,
        private readonly BehaviorProfileId $profileId,
        private readonly string $withdrawnBy,
        private readonly DateTimeImmutable $occurredAt,
    ) {
    }

    /**
     * The withdrawn proposal.
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
     * The identity that withdrew the proposal.
     */
    public function withdrawnBy(): string
    {
        return $this->withdrawnBy;
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
        return 'behavior.change_withdrawn';
    }

    /**
     * {@inheritDoc}
     */
    public function aggregateId(): string
    {
        return $this->proposalId->toString();
    }
}
