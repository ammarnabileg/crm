<?php

declare(strict_types=1);

namespace Nizam\Behavior\Application\Dto;

use Nizam\Behavior\Domain\BehaviorChangeProposal;
use Nizam\Behavior\Domain\ValueObject\EvidenceReference;

/**
 * A flat, read-only projection of a {@see BehaviorChangeProposal} aggregate.
 *
 * A proposal is the reviewable unit of the engine: the recommended traits together with everything a
 * human reviewer needs to decide — rationale, supporting approved evidence, confidence, business
 * impact, and an optional rollback target — plus who raised it and how it was decided. The view
 * carries all of that as scalars and plain arrays so it can be listed, serialized, and returned
 * across a bus without exposing domain objects.
 */
final class ProposalView
{
    /**
     * @param string                     $proposalId         The proposal's identity.
     * @param string                     $tenantId           The owning tenant.
     * @param string                     $roleId             The role the proposal targets.
     * @param string                     $profileId          The profile the proposal targets.
     * @param array<string, int|string>  $proposedTraits     The traits being proposed, as scalars.
     * @param string                     $rationale          Why the change is proposed.
     * @param list<array<string, mixed>> $supportingEvidence The approved evidence backing the proposal.
     * @param float                      $confidence         Confidence in the proposal, in [0, 1].
     * @param string                     $businessImpact     Stated business impact of the change.
     * @param int|null                   $rollbackToVersion  Prior version to restore, when a rollback proposal.
     * @param string                     $status             The proposal's lifecycle status value.
     * @param string                     $proposedBy         Identity that raised the proposal.
     * @param string                     $proposedAt         When the proposal was raised (ISO-8601).
     * @param string|null                $decidedBy          Identity that decided the proposal, when decided.
     * @param string|null                $decidedAt          When the proposal was decided (ISO-8601), when decided.
     */
    public function __construct(
        public readonly string $proposalId,
        public readonly string $tenantId,
        public readonly string $roleId,
        public readonly string $profileId,
        public readonly array $proposedTraits,
        public readonly string $rationale,
        public readonly array $supportingEvidence,
        public readonly float $confidence,
        public readonly string $businessImpact,
        public readonly ?int $rollbackToVersion,
        public readonly string $status,
        public readonly string $proposedBy,
        public readonly string $proposedAt,
        public readonly ?string $decidedBy,
        public readonly ?string $decidedAt,
    ) {
    }

    /**
     * Project a domain proposal into its read-only view.
     */
    public static function fromDomain(BehaviorChangeProposal $proposal): self
    {
        $evidence = array_map(
            static fn (EvidenceReference $reference): array => $reference->toArray(),
            $proposal->supportingEvidence(),
        );

        $decidedAt = $proposal->decidedAt();

        return new self(
            proposalId: $proposal->proposalId()->toString(),
            tenantId: $proposal->tenantId()->toString(),
            roleId: $proposal->roleId()->toString(),
            profileId: $proposal->profileId()->toString(),
            proposedTraits: $proposal->proposedTraits()->toArray(),
            rationale: $proposal->rationale(),
            supportingEvidence: array_values($evidence),
            confidence: $proposal->confidence(),
            businessImpact: $proposal->businessImpact(),
            rollbackToVersion: $proposal->rollbackToVersion(),
            status: $proposal->status()->value,
            proposedBy: $proposal->proposedBy(),
            proposedAt: $proposal->proposedAt()->format(\DateTimeInterface::ATOM),
            decidedBy: $proposal->decidedBy(),
            decidedAt: $decidedAt?->format(\DateTimeInterface::ATOM),
        );
    }

    /**
     * A scalar-only representation suitable for JSON serialization and transport.
     *
     * @return array{
     *     proposalId: string,
     *     tenantId: string,
     *     roleId: string,
     *     profileId: string,
     *     proposedTraits: array<string, int|string>,
     *     rationale: string,
     *     supportingEvidence: list<array<string, mixed>>,
     *     confidence: float,
     *     businessImpact: string,
     *     rollbackToVersion: int|null,
     *     status: string,
     *     proposedBy: string,
     *     proposedAt: string,
     *     decidedBy: string|null,
     *     decidedAt: string|null
     * }
     */
    public function toArray(): array
    {
        return [
            'proposalId' => $this->proposalId,
            'tenantId' => $this->tenantId,
            'roleId' => $this->roleId,
            'profileId' => $this->profileId,
            'proposedTraits' => $this->proposedTraits,
            'rationale' => $this->rationale,
            'supportingEvidence' => $this->supportingEvidence,
            'confidence' => $this->confidence,
            'businessImpact' => $this->businessImpact,
            'rollbackToVersion' => $this->rollbackToVersion,
            'status' => $this->status,
            'proposedBy' => $this->proposedBy,
            'proposedAt' => $this->proposedAt,
            'decidedBy' => $this->decidedBy,
            'decidedAt' => $this->decidedAt,
        ];
    }
}
