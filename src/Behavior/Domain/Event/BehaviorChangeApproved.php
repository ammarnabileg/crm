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
 * Recorded when a pending behavior change proposal is approved by a human.
 *
 * Approval marks the proposal {@see \Nizam\Behavior\Domain\Enum\ProposalStatus::Approved} and makes
 * it eligible to be applied to its target profile; the application step is a separate, explicit
 * action. Emitted by {@see \Nizam\Behavior\Domain\BehaviorChangeProposal::approve()}.
 */
final class BehaviorChangeApproved implements DomainEvent
{
    /**
     * @param ProposalId        $proposalId The approved proposal.
     * @param TenantId          $tenantId   The owning tenant.
     * @param RoleId            $roleId     The role the proposal targets.
     * @param BehaviorProfileId $profileId  The profile the proposal targets.
     * @param string            $approvedBy Identity that approved the proposal.
     * @param DateTimeImmutable $occurredAt When approval occurred.
     */
    public function __construct(
        private readonly ProposalId $proposalId,
        private readonly TenantId $tenantId,
        private readonly RoleId $roleId,
        private readonly BehaviorProfileId $profileId,
        private readonly string $approvedBy,
        private readonly DateTimeImmutable $occurredAt,
    ) {
    }

    /**
     * The approved proposal.
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
     * The identity that approved the proposal.
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
        return 'behavior.change_approved';
    }

    /**
     * {@inheritDoc}
     */
    public function aggregateId(): string
    {
        return $this->proposalId->toString();
    }
}
