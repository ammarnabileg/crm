<?php

declare(strict_types=1);

namespace Nizam\Behavior\Domain;

use DateTimeImmutable;
use Nizam\Behavior\Domain\Enum\ProposalStatus;
use Nizam\Behavior\Domain\Event\BehaviorChangeApproved;
use Nizam\Behavior\Domain\Event\BehaviorChangeProposed;
use Nizam\Behavior\Domain\Event\BehaviorChangeRejected;
use Nizam\Behavior\Domain\Event\BehaviorChangeWithdrawn;
use Nizam\Behavior\Domain\Exception\InvalidProposalTransitionException;
use Nizam\Behavior\Domain\ValueObject\BehaviorTraits;
use Nizam\Behavior\Domain\ValueObject\EvidenceReference;
use Nizam\Kernel\Domain\AggregateRoot;
use Nizam\Kernel\Domain\Clock;
use Nizam\Kernel\Domain\TenantId;
use Nizam\Platform\Support\Assert;

/**
 * A reviewable request to change a role's behavior profile — approval-gated, never auto-applied.
 *
 * The Behavior engine recommends; humans decide. A proposal bundles the recommended traits with the
 * full justification a reviewer needs — rationale, supporting approved evidence, a confidence, the
 * stated business impact, and an optional rollback target — and sits in
 * {@see ProposalStatus::Pending} until someone explicitly approves, rejects, or withdraws it.
 * Approving a proposal changes nothing on its own: applying it to the target
 * {@see BehaviorProfile} is a separate, deliberate step. Each decision records a domain event.
 */
final class BehaviorChangeProposal extends AggregateRoot
{
    /**
     * @param ProposalId              $id                 The proposal's identity.
     * @param TenantId                $tenantId           The owning tenant.
     * @param RoleId                  $roleId             The role the proposal targets.
     * @param BehaviorProfileId       $profileId          The profile the proposal targets.
     * @param BehaviorTraits          $proposedTraits     The traits being proposed.
     * @param string                  $rationale          Why the change is proposed.
     * @param list<EvidenceReference> $supportingEvidence Approved evidence backing the proposal.
     * @param float                   $confidence         Confidence in the proposal, in [0, 1].
     * @param string                  $businessImpact     Stated business impact of the change.
     * @param int|null                $rollbackToVersion  Prior version to restore, when the proposal is a rollback.
     * @param ProposalStatus          $status             The proposal's lifecycle status.
     * @param string                  $proposedBy         Identity that raised the proposal.
     * @param DateTimeImmutable       $proposedAt         When the proposal was raised.
     * @param string|null             $decidedBy          Identity that decided the proposal, when decided.
     * @param DateTimeImmutable|null  $decidedAt          When the proposal was decided, when decided.
     */
    private function __construct(
        ProposalId $id,
        private readonly TenantId $tenantId,
        private readonly RoleId $roleId,
        private readonly BehaviorProfileId $profileId,
        private readonly BehaviorTraits $proposedTraits,
        private readonly string $rationale,
        private readonly array $supportingEvidence,
        private readonly float $confidence,
        private readonly string $businessImpact,
        private readonly ?int $rollbackToVersion,
        private ProposalStatus $status,
        private readonly string $proposedBy,
        private readonly DateTimeImmutable $proposedAt,
        private ?string $decidedBy,
        private ?DateTimeImmutable $decidedAt,
    ) {
        parent::__construct($id);
    }

    /**
     * Raise a new, pending behavior change proposal.
     *
     * Records a {@see BehaviorChangeProposed} event. Nothing about production behavior changes.
     *
     * @param ProposalId              $id                 The identity to assign.
     * @param TenantId                $tenantId           The owning tenant.
     * @param RoleId                  $roleId             The role the proposal targets.
     * @param BehaviorProfileId       $profileId          The profile the proposal targets.
     * @param BehaviorTraits          $proposedTraits     The traits being proposed.
     * @param string                  $rationale          Why the change is proposed.
     * @param list<EvidenceReference> $supportingEvidence Approved evidence backing the proposal (non-empty).
     * @param float                   $confidence         Confidence in the proposal, in [0, 1].
     * @param string                  $businessImpact     Stated business impact of the change.
     * @param string                  $proposedBy         Identity raising the proposal.
     * @param Clock                   $clock              Time source.
     * @param int|null                $rollbackToVersion  Prior version to restore, when the proposal is a rollback.
     */
    public static function propose(
        ProposalId $id,
        TenantId $tenantId,
        RoleId $roleId,
        BehaviorProfileId $profileId,
        BehaviorTraits $proposedTraits,
        string $rationale,
        array $supportingEvidence,
        float $confidence,
        string $businessImpact,
        string $proposedBy,
        Clock $clock,
        ?int $rollbackToVersion = null,
    ): self {
        Assert::notEmpty($rationale, 'A proposal must state a rationale.');
        Assert::notEmpty($businessImpact, 'A proposal must state its business impact.');
        Assert::notEmpty($proposedBy, 'A proposal must record who proposed it.');
        Assert::that(
            $confidence >= 0.0 && $confidence <= 1.0,
            'Proposal confidence must be within the inclusive range [0, 1].',
        );
        Assert::that(
            $supportingEvidence !== [],
            'A proposal must cite at least one supporting evidence.',
        );
        foreach ($supportingEvidence as $reference) {
            Assert::that(
                $reference instanceof EvidenceReference,
                'Proposal supportingEvidence must contain only EvidenceReference instances.',
            );
        }
        if ($rollbackToVersion !== null) {
            Assert::positive($rollbackToVersion, 'A proposal rollbackToVersion must be a positive integer.');
        }

        $now = $clock->now();
        $proposal = new self(
            id: $id,
            tenantId: $tenantId,
            roleId: $roleId,
            profileId: $profileId,
            proposedTraits: $proposedTraits,
            rationale: $rationale,
            supportingEvidence: array_values($supportingEvidence),
            confidence: $confidence,
            businessImpact: $businessImpact,
            rollbackToVersion: $rollbackToVersion,
            status: ProposalStatus::Pending,
            proposedBy: $proposedBy,
            proposedAt: $now,
            decidedBy: null,
            decidedAt: null,
        );

        $proposal->recordThat(new BehaviorChangeProposed(
            $id,
            $tenantId,
            $roleId,
            $profileId,
            $confidence,
            $proposedBy,
            $now,
        ));

        return $proposal;
    }

    /**
     * Reconstitute a proposal from persisted state without emitting events.
     *
     * @param list<EvidenceReference> $supportingEvidence The approved evidence backing the proposal.
     */
    public static function reconstitute(
        ProposalId $id,
        TenantId $tenantId,
        RoleId $roleId,
        BehaviorProfileId $profileId,
        BehaviorTraits $proposedTraits,
        string $rationale,
        array $supportingEvidence,
        float $confidence,
        string $businessImpact,
        ?int $rollbackToVersion,
        ProposalStatus $status,
        string $proposedBy,
        DateTimeImmutable $proposedAt,
        ?string $decidedBy,
        ?DateTimeImmutable $decidedAt,
    ): self {
        return new self(
            id: $id,
            tenantId: $tenantId,
            roleId: $roleId,
            profileId: $profileId,
            proposedTraits: $proposedTraits,
            rationale: $rationale,
            supportingEvidence: array_values($supportingEvidence),
            confidence: $confidence,
            businessImpact: $businessImpact,
            rollbackToVersion: $rollbackToVersion,
            status: $status,
            proposedBy: $proposedBy,
            proposedAt: $proposedAt,
            decidedBy: $decidedBy,
            decidedAt: $decidedAt,
        );
    }

    /**
     * Approve the proposal, making it eligible to be applied to its target profile.
     *
     * Applying the change is a separate action; approval alone mutates no behavior. Records a
     * {@see BehaviorChangeApproved} event.
     *
     * @param string $approvedBy Identity approving the proposal.
     * @param Clock  $clock      Time source.
     *
     * @throws InvalidProposalTransitionException When the proposal is no longer pending.
     */
    public function approve(string $approvedBy, Clock $clock): void
    {
        Assert::notEmpty($approvedBy, 'Approval must record who approved it.');
        $this->guardPending('approve');

        $now = $clock->now();
        $this->status = ProposalStatus::Approved;
        $this->decidedBy = $approvedBy;
        $this->decidedAt = $now;

        $this->recordThat(new BehaviorChangeApproved(
            $this->proposalId(),
            $this->tenantId,
            $this->roleId,
            $this->profileId,
            $approvedBy,
            $now,
        ));
    }

    /**
     * Reject the proposal so it will never be applied.
     *
     * Records a {@see BehaviorChangeRejected} event carrying the reason.
     *
     * @param string $reason     Why the proposal is rejected.
     * @param string $rejectedBy Identity rejecting the proposal.
     * @param Clock  $clock      Time source.
     *
     * @throws InvalidProposalTransitionException When the proposal is no longer pending.
     */
    public function reject(string $reason, string $rejectedBy, Clock $clock): void
    {
        Assert::notEmpty($reason, 'A rejection must state a reason.');
        Assert::notEmpty($rejectedBy, 'Rejection must record who rejected it.');
        $this->guardPending('reject');

        $now = $clock->now();
        $this->status = ProposalStatus::Rejected;
        $this->decidedBy = $rejectedBy;
        $this->decidedAt = $now;

        $this->recordThat(new BehaviorChangeRejected(
            $this->proposalId(),
            $this->tenantId,
            $this->roleId,
            $this->profileId,
            $reason,
            $rejectedBy,
            $now,
        ));
    }

    /**
     * Withdraw the proposal before a decision is made.
     *
     * Records a {@see BehaviorChangeWithdrawn} event.
     *
     * @param string $withdrawnBy Identity withdrawing the proposal.
     * @param Clock  $clock       Time source.
     *
     * @throws InvalidProposalTransitionException When the proposal is no longer pending.
     */
    public function withdraw(string $withdrawnBy, Clock $clock): void
    {
        Assert::notEmpty($withdrawnBy, 'Withdrawal must record who withdrew it.');
        $this->guardPending('withdraw');

        $now = $clock->now();
        $this->status = ProposalStatus::Withdrawn;
        $this->decidedBy = $withdrawnBy;
        $this->decidedAt = $now;

        $this->recordThat(new BehaviorChangeWithdrawn(
            $this->proposalId(),
            $this->tenantId,
            $this->roleId,
            $this->profileId,
            $withdrawnBy,
            $now,
        ));
    }

    /**
     * The proposal's identity, narrowed to {@see ProposalId}.
     */
    public function proposalId(): ProposalId
    {
        $id = $this->id();
        assert($id instanceof ProposalId);

        return $id;
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
     * The traits being proposed.
     */
    public function proposedTraits(): BehaviorTraits
    {
        return $this->proposedTraits;
    }

    /**
     * Why the change is proposed.
     */
    public function rationale(): string
    {
        return $this->rationale;
    }

    /**
     * The approved evidence backing the proposal.
     *
     * @return list<EvidenceReference>
     */
    public function supportingEvidence(): array
    {
        return $this->supportingEvidence;
    }

    /**
     * The confidence in the proposal, in [0, 1].
     */
    public function confidence(): float
    {
        return $this->confidence;
    }

    /**
     * The stated business impact of the change.
     */
    public function businessImpact(): string
    {
        return $this->businessImpact;
    }

    /**
     * The prior version to restore, when the proposal is a rollback; otherwise null.
     */
    public function rollbackToVersion(): ?int
    {
        return $this->rollbackToVersion;
    }

    /**
     * The proposal's lifecycle status.
     */
    public function status(): ProposalStatus
    {
        return $this->status;
    }

    /**
     * The identity that raised the proposal.
     */
    public function proposedBy(): string
    {
        return $this->proposedBy;
    }

    /**
     * When the proposal was raised.
     */
    public function proposedAt(): DateTimeImmutable
    {
        return $this->proposedAt;
    }

    /**
     * The identity that decided the proposal, when decided; otherwise null.
     */
    public function decidedBy(): ?string
    {
        return $this->decidedBy;
    }

    /**
     * When the proposal was decided, when decided; otherwise null.
     */
    public function decidedAt(): ?DateTimeImmutable
    {
        return $this->decidedAt;
    }

    /**
     * Guard that the proposal is still pending before a decision transition.
     *
     * @throws InvalidProposalTransitionException When the proposal is no longer pending.
     */
    private function guardPending(string $operation): void
    {
        if (!$this->status->isPending()) {
            throw InvalidProposalTransitionException::forOperation($operation, $this->status);
        }
    }
}
