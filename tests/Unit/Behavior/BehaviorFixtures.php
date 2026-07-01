<?php

declare(strict_types=1);

namespace Nizam\Tests\Unit\Behavior;

use DateTimeImmutable;
use Nizam\Behavior\Domain\BehaviorObservation;
use Nizam\Behavior\Domain\Enum\ApprovalStyle;
use Nizam\Behavior\Domain\Enum\CommunicationStyle;
use Nizam\Behavior\Domain\Enum\CustomerInteractionStyle;
use Nizam\Behavior\Domain\Enum\DecisionStyle;
use Nizam\Behavior\Domain\Enum\DelegationStrategy;
use Nizam\Behavior\Domain\Enum\DocumentationStyle;
use Nizam\Behavior\Domain\Enum\EscalationStyle;
use Nizam\Behavior\Domain\Enum\FollowUpStrategy;
use Nizam\Behavior\Domain\Enum\MeetingStyle;
use Nizam\Behavior\Domain\Enum\NegotiationStyle;
use Nizam\Behavior\Domain\Enum\ObservationSourceType;
use Nizam\Behavior\Domain\Enum\PlanningStrategy;
use Nizam\Behavior\Domain\Enum\PriorityStrategy;
use Nizam\Behavior\Domain\Enum\QualityExpectation;
use Nizam\Behavior\Domain\Enum\RiskTolerance;
use Nizam\Behavior\Domain\ObservationId;
use Nizam\Behavior\Domain\RoleId;
use Nizam\Behavior\Domain\ValueObject\BehaviorTraits;
use Nizam\Behavior\Domain\ValueObject\EvidenceReference;
use Nizam\Kernel\Domain\TenantId;

/**
 * Shared, deterministic factory helpers for Behavior unit tests.
 *
 * Building a {@see BehaviorTraits} requires fourteen enum arguments; centralizing sane defaults here
 * keeps each test focused on the one axis it exercises. Every factory returns a fully valid domain
 * object, so tests can override only what they care about via the `with*()` copy-methods.
 */
trait BehaviorFixtures
{
    /**
     * A complete, valid, non-elevated trait set with a configurable evidence requirement.
     *
     * @param int $evidenceRequirements Minimum approved evidences per change (>= 1).
     */
    private function traits(int $evidenceRequirements = 1): BehaviorTraits
    {
        return BehaviorTraits::create(
            decisionStyle: DecisionStyle::DataDriven,
            communicationStyle: CommunicationStyle::Concise,
            approvalStyle: ApprovalStyle::SingleApprover,
            escalationStyle: EscalationStyle::OnThreshold,
            riskTolerance: RiskTolerance::Balanced,
            priorityStrategy: PriorityStrategy::DeadlineFirst,
            delegationStrategy: DelegationStrategy::DelegateWithReview,
            planningStrategy: PlanningStrategy::Structured,
            followUpStrategy: FollowUpStrategy::Scheduled,
            documentationStyle: DocumentationStyle::Standard,
            meetingStyle: MeetingStyle::BriefSync,
            negotiationStyle: NegotiationStyle::Principled,
            customerInteractionStyle: CustomerInteractionStyle::Proactive,
            qualityExpectation: QualityExpectation::High,
            evidenceRequirements: $evidenceRequirements,
        );
    }

    /**
     * A single evidence reference with a stable, unique reference id and configurable weight.
     *
     * @param array<string, string> $observedTraits Style-axis values this practice attests to.
     */
    private function evidence(
        string $referenceId,
        float $weight = 1.0,
        ?DateTimeImmutable $occurredAt = null,
        ObservationSourceType $sourceType = ObservationSourceType::ApprovedDecision,
        array $observedTraits = [],
    ): EvidenceReference {
        return new EvidenceReference(
            sourceType: $sourceType,
            referenceId: $referenceId,
            summary: 'Approved practice ' . $referenceId,
            occurredAt: $occurredAt ?? new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
            weight: $weight,
            observedTraits: $observedTraits,
        );
    }

    /**
     * An approved observation for a role, wrapping a fresh evidence reference.
     *
     * @param array<string, string> $observedTraits Style-axis values this practice attests to.
     */
    private function approvedObservation(
        RoleId $roleId,
        TenantId $tenantId,
        string $referenceId,
        float $weight = 1.0,
        ?DateTimeImmutable $occurredAt = null,
        array $observedTraits = [],
    ): BehaviorObservation {
        return BehaviorObservation::approved(
            ObservationId::generate(),
            $tenantId,
            $roleId,
            $this->evidence($referenceId, $weight, $occurredAt, observedTraits: $observedTraits),
            'manager@nizam.test',
            $occurredAt ?? new DateTimeImmutable('2026-01-02T00:00:00+00:00'),
        );
    }
}
