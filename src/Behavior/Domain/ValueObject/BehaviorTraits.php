<?php

declare(strict_types=1);

namespace Nizam\Behavior\Domain\ValueObject;

use Nizam\Behavior\Domain\Enum\ApprovalStyle;
use Nizam\Behavior\Domain\Enum\BehaviorTraitAxis;
use Nizam\Behavior\Domain\Enum\CommunicationStyle;
use Nizam\Behavior\Domain\Enum\CustomerInteractionStyle;
use Nizam\Behavior\Domain\Enum\DecisionStyle;
use Nizam\Behavior\Domain\Enum\DelegationStrategy;
use Nizam\Behavior\Domain\Enum\DocumentationStyle;
use Nizam\Behavior\Domain\Enum\EscalationStyle;
use Nizam\Behavior\Domain\Enum\FollowUpStrategy;
use Nizam\Behavior\Domain\Enum\MeetingStyle;
use Nizam\Behavior\Domain\Enum\NegotiationStyle;
use Nizam\Behavior\Domain\Enum\PlanningStrategy;
use Nizam\Behavior\Domain\Enum\PriorityStrategy;
use Nizam\Behavior\Domain\Enum\QualityExpectation;
use Nizam\Behavior\Domain\Enum\RiskTolerance;
use Nizam\Behavior\Domain\Exception\ElevatedRiskNotAllowedException;
use Nizam\Kernel\Domain\ValueObject;
use Nizam\Platform\Exception\InvalidArgumentException;
use Nizam\Platform\Support\Assert;

/**
 * The complete, immutable description of how a role performs work.
 *
 * A behavior profile is, at any version, exactly one {@see BehaviorTraits}: fourteen orthogonal
 * style axes (each a backed enum) plus `evidenceRequirements`, the minimum count of approved
 * evidences a change must supply before it may alter these traits. The object is immutable — every
 * mutation returns a fresh copy through a `with*()` method — and self-validating.
 *
 * Adopting {@see RiskTolerance::Elevated} is privileged: it can only be constructed through the
 * elevated-risk factory guard ({@see self::withPolicyAllowance()} / the `$policyAllowsElevatedRisk`
 * flag on {@see self::create()}), which enforces that a caller has asserted a policy allowance.
 * The plain {@see self::create()} entry point forbids elevated risk outright.
 */
final class BehaviorTraits implements ValueObject
{
    /**
     * @param DecisionStyle            $decisionStyle            How the role reaches decisions.
     * @param CommunicationStyle       $communicationStyle       How the role communicates.
     * @param ApprovalStyle            $approvalStyle            How the role routes approvals.
     * @param EscalationStyle          $escalationStyle          When the role escalates.
     * @param RiskTolerance            $riskTolerance            How much risk the role accepts.
     * @param PriorityStrategy         $priorityStrategy         How the role orders work.
     * @param DelegationStrategy       $delegationStrategy       How the role delegates.
     * @param PlanningStrategy         $planningStrategy         How the role plans.
     * @param FollowUpStrategy         $followUpStrategy         How the role follows up.
     * @param DocumentationStyle       $documentationStyle       How thoroughly the role documents.
     * @param MeetingStyle             $meetingStyle             How the role runs coordination.
     * @param NegotiationStyle         $negotiationStyle         How the role negotiates.
     * @param CustomerInteractionStyle $customerInteractionStyle How the role engages customers.
     * @param QualityExpectation       $qualityExpectation       The role's quality bar.
     * @param int                      $evidenceRequirements     Minimum approved evidences per change (>= 1).
     */
    private function __construct(
        private readonly DecisionStyle $decisionStyle,
        private readonly CommunicationStyle $communicationStyle,
        private readonly ApprovalStyle $approvalStyle,
        private readonly EscalationStyle $escalationStyle,
        private readonly RiskTolerance $riskTolerance,
        private readonly PriorityStrategy $priorityStrategy,
        private readonly DelegationStrategy $delegationStrategy,
        private readonly PlanningStrategy $planningStrategy,
        private readonly FollowUpStrategy $followUpStrategy,
        private readonly DocumentationStyle $documentationStyle,
        private readonly MeetingStyle $meetingStyle,
        private readonly NegotiationStyle $negotiationStyle,
        private readonly CustomerInteractionStyle $customerInteractionStyle,
        private readonly QualityExpectation $qualityExpectation,
        private readonly int $evidenceRequirements,
    ) {
        Assert::positive(
            $evidenceRequirements,
            'evidenceRequirements must be a positive integer (at least one approved evidence per change).',
        );
    }

    /**
     * Construct a trait set, forbidding privileged elevated risk tolerance.
     *
     * Use this for the common case. If the traits request {@see RiskTolerance::Elevated}, this
     * throws — callers that legitimately need elevated risk must go through
     * {@see self::withPolicyAllowance()} and prove a policy allowance.
     *
     * @param int $evidenceRequirements Minimum approved evidences required to change these traits.
     *
     * @throws ElevatedRiskNotAllowedException When elevated risk is requested without a policy allowance.
     */
    public static function create(
        DecisionStyle $decisionStyle,
        CommunicationStyle $communicationStyle,
        ApprovalStyle $approvalStyle,
        EscalationStyle $escalationStyle,
        RiskTolerance $riskTolerance,
        PriorityStrategy $priorityStrategy,
        DelegationStrategy $delegationStrategy,
        PlanningStrategy $planningStrategy,
        FollowUpStrategy $followUpStrategy,
        DocumentationStyle $documentationStyle,
        MeetingStyle $meetingStyle,
        NegotiationStyle $negotiationStyle,
        CustomerInteractionStyle $customerInteractionStyle,
        QualityExpectation $qualityExpectation,
        int $evidenceRequirements,
    ): self {
        return self::withPolicyAllowance(
            $decisionStyle,
            $communicationStyle,
            $approvalStyle,
            $escalationStyle,
            $riskTolerance,
            $priorityStrategy,
            $delegationStrategy,
            $planningStrategy,
            $followUpStrategy,
            $documentationStyle,
            $meetingStyle,
            $negotiationStyle,
            $customerInteractionStyle,
            $qualityExpectation,
            $evidenceRequirements,
            false,
        );
    }

    /**
     * Construct a trait set, allowing elevated risk tolerance only when policy permits it.
     *
     * The elevated-risk factory guard: when the traits request {@see RiskTolerance::Elevated} the
     * `$policyAllowsElevatedRisk` flag MUST be true (the caller having consulted the tenant/role
     * {@see \Nizam\Behavior\Domain\Service\RiskTolerancePolicy}); otherwise construction fails.
     *
     * @param int  $evidenceRequirements    Minimum approved evidences required to change these traits.
     * @param bool $policyAllowsElevatedRisk Whether tenant/role policy permits elevated risk.
     *
     * @throws ElevatedRiskNotAllowedException When elevated risk is requested but not permitted.
     */
    public static function withPolicyAllowance(
        DecisionStyle $decisionStyle,
        CommunicationStyle $communicationStyle,
        ApprovalStyle $approvalStyle,
        EscalationStyle $escalationStyle,
        RiskTolerance $riskTolerance,
        PriorityStrategy $priorityStrategy,
        DelegationStrategy $delegationStrategy,
        PlanningStrategy $planningStrategy,
        FollowUpStrategy $followUpStrategy,
        DocumentationStyle $documentationStyle,
        MeetingStyle $meetingStyle,
        NegotiationStyle $negotiationStyle,
        CustomerInteractionStyle $customerInteractionStyle,
        QualityExpectation $qualityExpectation,
        int $evidenceRequirements,
        bool $policyAllowsElevatedRisk,
    ): self {
        if ($riskTolerance->requiresElevatedRiskPolicy() && !$policyAllowsElevatedRisk) {
            throw ElevatedRiskNotAllowedException::create();
        }

        return new self(
            $decisionStyle,
            $communicationStyle,
            $approvalStyle,
            $escalationStyle,
            $riskTolerance,
            $priorityStrategy,
            $delegationStrategy,
            $planningStrategy,
            $followUpStrategy,
            $documentationStyle,
            $meetingStyle,
            $negotiationStyle,
            $customerInteractionStyle,
            $qualityExpectation,
            $evidenceRequirements,
        );
    }

    /** How the role reaches decisions. */
    public function decisionStyle(): DecisionStyle
    {
        return $this->decisionStyle;
    }

    /** How the role communicates. */
    public function communicationStyle(): CommunicationStyle
    {
        return $this->communicationStyle;
    }

    /** How the role routes approvals. */
    public function approvalStyle(): ApprovalStyle
    {
        return $this->approvalStyle;
    }

    /** When the role escalates. */
    public function escalationStyle(): EscalationStyle
    {
        return $this->escalationStyle;
    }

    /** How much risk the role accepts. */
    public function riskTolerance(): RiskTolerance
    {
        return $this->riskTolerance;
    }

    /** How the role orders work. */
    public function priorityStrategy(): PriorityStrategy
    {
        return $this->priorityStrategy;
    }

    /** How the role delegates. */
    public function delegationStrategy(): DelegationStrategy
    {
        return $this->delegationStrategy;
    }

    /** How the role plans. */
    public function planningStrategy(): PlanningStrategy
    {
        return $this->planningStrategy;
    }

    /** How the role follows up. */
    public function followUpStrategy(): FollowUpStrategy
    {
        return $this->followUpStrategy;
    }

    /** How thoroughly the role documents. */
    public function documentationStyle(): DocumentationStyle
    {
        return $this->documentationStyle;
    }

    /** How the role runs coordination. */
    public function meetingStyle(): MeetingStyle
    {
        return $this->meetingStyle;
    }

    /** How the role negotiates. */
    public function negotiationStyle(): NegotiationStyle
    {
        return $this->negotiationStyle;
    }

    /** How the role engages customers. */
    public function customerInteractionStyle(): CustomerInteractionStyle
    {
        return $this->customerInteractionStyle;
    }

    /** The role's quality bar. */
    public function qualityExpectation(): QualityExpectation
    {
        return $this->qualityExpectation;
    }

    /** Minimum approved evidences required to change these traits. */
    public function evidenceRequirements(): int
    {
        return $this->evidenceRequirements;
    }

    /**
     * Copy with a different decision style.
     */
    public function withDecisionStyle(DecisionStyle $value): self
    {
        return new self(
            $value,
            $this->communicationStyle,
            $this->approvalStyle,
            $this->escalationStyle,
            $this->riskTolerance,
            $this->priorityStrategy,
            $this->delegationStrategy,
            $this->planningStrategy,
            $this->followUpStrategy,
            $this->documentationStyle,
            $this->meetingStyle,
            $this->negotiationStyle,
            $this->customerInteractionStyle,
            $this->qualityExpectation,
            $this->evidenceRequirements,
        );
    }

    /**
     * Copy with a different communication style.
     */
    public function withCommunicationStyle(CommunicationStyle $value): self
    {
        return new self(
            $this->decisionStyle,
            $value,
            $this->approvalStyle,
            $this->escalationStyle,
            $this->riskTolerance,
            $this->priorityStrategy,
            $this->delegationStrategy,
            $this->planningStrategy,
            $this->followUpStrategy,
            $this->documentationStyle,
            $this->meetingStyle,
            $this->negotiationStyle,
            $this->customerInteractionStyle,
            $this->qualityExpectation,
            $this->evidenceRequirements,
        );
    }

    /**
     * Copy with a different approval style.
     */
    public function withApprovalStyle(ApprovalStyle $value): self
    {
        return new self(
            $this->decisionStyle,
            $this->communicationStyle,
            $value,
            $this->escalationStyle,
            $this->riskTolerance,
            $this->priorityStrategy,
            $this->delegationStrategy,
            $this->planningStrategy,
            $this->followUpStrategy,
            $this->documentationStyle,
            $this->meetingStyle,
            $this->negotiationStyle,
            $this->customerInteractionStyle,
            $this->qualityExpectation,
            $this->evidenceRequirements,
        );
    }

    /**
     * Copy with a different escalation style.
     */
    public function withEscalationStyle(EscalationStyle $value): self
    {
        return new self(
            $this->decisionStyle,
            $this->communicationStyle,
            $this->approvalStyle,
            $value,
            $this->riskTolerance,
            $this->priorityStrategy,
            $this->delegationStrategy,
            $this->planningStrategy,
            $this->followUpStrategy,
            $this->documentationStyle,
            $this->meetingStyle,
            $this->negotiationStyle,
            $this->customerInteractionStyle,
            $this->qualityExpectation,
            $this->evidenceRequirements,
        );
    }

    /**
     * Copy with a different risk tolerance, enforcing the elevated-risk policy guard.
     *
     * @param bool $policyAllowsElevatedRisk Whether tenant/role policy permits elevated risk.
     *
     * @throws ElevatedRiskNotAllowedException When elevated risk is requested but not permitted.
     */
    public function withRiskTolerance(RiskTolerance $value, bool $policyAllowsElevatedRisk = false): self
    {
        if ($value->requiresElevatedRiskPolicy() && !$policyAllowsElevatedRisk) {
            throw ElevatedRiskNotAllowedException::create();
        }

        return new self(
            $this->decisionStyle,
            $this->communicationStyle,
            $this->approvalStyle,
            $this->escalationStyle,
            $value,
            $this->priorityStrategy,
            $this->delegationStrategy,
            $this->planningStrategy,
            $this->followUpStrategy,
            $this->documentationStyle,
            $this->meetingStyle,
            $this->negotiationStyle,
            $this->customerInteractionStyle,
            $this->qualityExpectation,
            $this->evidenceRequirements,
        );
    }

    /**
     * Copy with a different priority strategy.
     */
    public function withPriorityStrategy(PriorityStrategy $value): self
    {
        return new self(
            $this->decisionStyle,
            $this->communicationStyle,
            $this->approvalStyle,
            $this->escalationStyle,
            $this->riskTolerance,
            $value,
            $this->delegationStrategy,
            $this->planningStrategy,
            $this->followUpStrategy,
            $this->documentationStyle,
            $this->meetingStyle,
            $this->negotiationStyle,
            $this->customerInteractionStyle,
            $this->qualityExpectation,
            $this->evidenceRequirements,
        );
    }

    /**
     * Copy with a different delegation strategy.
     */
    public function withDelegationStrategy(DelegationStrategy $value): self
    {
        return new self(
            $this->decisionStyle,
            $this->communicationStyle,
            $this->approvalStyle,
            $this->escalationStyle,
            $this->riskTolerance,
            $this->priorityStrategy,
            $value,
            $this->planningStrategy,
            $this->followUpStrategy,
            $this->documentationStyle,
            $this->meetingStyle,
            $this->negotiationStyle,
            $this->customerInteractionStyle,
            $this->qualityExpectation,
            $this->evidenceRequirements,
        );
    }

    /**
     * Copy with a different planning strategy.
     */
    public function withPlanningStrategy(PlanningStrategy $value): self
    {
        return new self(
            $this->decisionStyle,
            $this->communicationStyle,
            $this->approvalStyle,
            $this->escalationStyle,
            $this->riskTolerance,
            $this->priorityStrategy,
            $this->delegationStrategy,
            $value,
            $this->followUpStrategy,
            $this->documentationStyle,
            $this->meetingStyle,
            $this->negotiationStyle,
            $this->customerInteractionStyle,
            $this->qualityExpectation,
            $this->evidenceRequirements,
        );
    }

    /**
     * Copy with a different follow-up strategy.
     */
    public function withFollowUpStrategy(FollowUpStrategy $value): self
    {
        return new self(
            $this->decisionStyle,
            $this->communicationStyle,
            $this->approvalStyle,
            $this->escalationStyle,
            $this->riskTolerance,
            $this->priorityStrategy,
            $this->delegationStrategy,
            $this->planningStrategy,
            $value,
            $this->documentationStyle,
            $this->meetingStyle,
            $this->negotiationStyle,
            $this->customerInteractionStyle,
            $this->qualityExpectation,
            $this->evidenceRequirements,
        );
    }

    /**
     * Copy with a different documentation style.
     */
    public function withDocumentationStyle(DocumentationStyle $value): self
    {
        return new self(
            $this->decisionStyle,
            $this->communicationStyle,
            $this->approvalStyle,
            $this->escalationStyle,
            $this->riskTolerance,
            $this->priorityStrategy,
            $this->delegationStrategy,
            $this->planningStrategy,
            $this->followUpStrategy,
            $value,
            $this->meetingStyle,
            $this->negotiationStyle,
            $this->customerInteractionStyle,
            $this->qualityExpectation,
            $this->evidenceRequirements,
        );
    }

    /**
     * Copy with a different meeting style.
     */
    public function withMeetingStyle(MeetingStyle $value): self
    {
        return new self(
            $this->decisionStyle,
            $this->communicationStyle,
            $this->approvalStyle,
            $this->escalationStyle,
            $this->riskTolerance,
            $this->priorityStrategy,
            $this->delegationStrategy,
            $this->planningStrategy,
            $this->followUpStrategy,
            $this->documentationStyle,
            $value,
            $this->negotiationStyle,
            $this->customerInteractionStyle,
            $this->qualityExpectation,
            $this->evidenceRequirements,
        );
    }

    /**
     * Copy with a different negotiation style.
     */
    public function withNegotiationStyle(NegotiationStyle $value): self
    {
        return new self(
            $this->decisionStyle,
            $this->communicationStyle,
            $this->approvalStyle,
            $this->escalationStyle,
            $this->riskTolerance,
            $this->priorityStrategy,
            $this->delegationStrategy,
            $this->planningStrategy,
            $this->followUpStrategy,
            $this->documentationStyle,
            $this->meetingStyle,
            $value,
            $this->customerInteractionStyle,
            $this->qualityExpectation,
            $this->evidenceRequirements,
        );
    }

    /**
     * Copy with a different customer interaction style.
     */
    public function withCustomerInteractionStyle(CustomerInteractionStyle $value): self
    {
        return new self(
            $this->decisionStyle,
            $this->communicationStyle,
            $this->approvalStyle,
            $this->escalationStyle,
            $this->riskTolerance,
            $this->priorityStrategy,
            $this->delegationStrategy,
            $this->planningStrategy,
            $this->followUpStrategy,
            $this->documentationStyle,
            $this->meetingStyle,
            $this->negotiationStyle,
            $value,
            $this->qualityExpectation,
            $this->evidenceRequirements,
        );
    }

    /**
     * Copy with a different quality expectation.
     */
    public function withQualityExpectation(QualityExpectation $value): self
    {
        return new self(
            $this->decisionStyle,
            $this->communicationStyle,
            $this->approvalStyle,
            $this->escalationStyle,
            $this->riskTolerance,
            $this->priorityStrategy,
            $this->delegationStrategy,
            $this->planningStrategy,
            $this->followUpStrategy,
            $this->documentationStyle,
            $this->meetingStyle,
            $this->negotiationStyle,
            $this->customerInteractionStyle,
            $value,
            $this->evidenceRequirements,
        );
    }

    /**
     * Copy with a different minimum evidence requirement.
     */
    public function withEvidenceRequirements(int $value): self
    {
        return new self(
            $this->decisionStyle,
            $this->communicationStyle,
            $this->approvalStyle,
            $this->escalationStyle,
            $this->riskTolerance,
            $this->priorityStrategy,
            $this->delegationStrategy,
            $this->planningStrategy,
            $this->followUpStrategy,
            $this->documentationStyle,
            $this->meetingStyle,
            $this->negotiationStyle,
            $this->customerInteractionStyle,
            $this->qualityExpectation,
            $value,
        );
    }

    /**
     * The current backed-enum value of one style axis, addressed by axis.
     *
     * Lets the domain read any of the fourteen style axes generically — the basis for deterministic,
     * per-axis consolidation without a hand-written switch at every call site.
     */
    public function axis(BehaviorTraitAxis $axis): \BackedEnum
    {
        return match ($axis) {
            BehaviorTraitAxis::DecisionStyle => $this->decisionStyle,
            BehaviorTraitAxis::CommunicationStyle => $this->communicationStyle,
            BehaviorTraitAxis::ApprovalStyle => $this->approvalStyle,
            BehaviorTraitAxis::EscalationStyle => $this->escalationStyle,
            BehaviorTraitAxis::RiskTolerance => $this->riskTolerance,
            BehaviorTraitAxis::PriorityStrategy => $this->priorityStrategy,
            BehaviorTraitAxis::DelegationStrategy => $this->delegationStrategy,
            BehaviorTraitAxis::PlanningStrategy => $this->planningStrategy,
            BehaviorTraitAxis::FollowUpStrategy => $this->followUpStrategy,
            BehaviorTraitAxis::DocumentationStyle => $this->documentationStyle,
            BehaviorTraitAxis::MeetingStyle => $this->meetingStyle,
            BehaviorTraitAxis::NegotiationStyle => $this->negotiationStyle,
            BehaviorTraitAxis::CustomerInteractionStyle => $this->customerInteractionStyle,
            BehaviorTraitAxis::QualityExpectation => $this->qualityExpectation,
        };
    }

    /**
     * Copy this set with one style axis set to a new value, addressed by axis.
     *
     * The value's concrete enum type must match the axis (enforced), routing through the same
     * type-safe `with*()` copy-method the axis maps to. Elevated risk still passes through the
     * policy guard, so this method cannot smuggle a privileged value past it.
     *
     * @param bool $policyAllowsElevatedRisk Whether tenant/role policy permits elevated risk (risk axis only).
     *
     * @throws InvalidArgumentException          When the value's type does not match the axis.
     * @throws ElevatedRiskNotAllowedException   When elevated risk is requested but not permitted.
     */
    public function withAxis(BehaviorTraitAxis $axis, \BackedEnum $value, bool $policyAllowsElevatedRisk = false): self
    {
        $expected = $axis->enumClass();
        Assert::that(
            $value instanceof $expected,
            sprintf('Value for axis "%s" must be an instance of %s.', $axis->value, $expected),
        );

        return match ($axis) {
            BehaviorTraitAxis::DecisionStyle => $this->withDecisionStyle($value),
            BehaviorTraitAxis::CommunicationStyle => $this->withCommunicationStyle($value),
            BehaviorTraitAxis::ApprovalStyle => $this->withApprovalStyle($value),
            BehaviorTraitAxis::EscalationStyle => $this->withEscalationStyle($value),
            BehaviorTraitAxis::RiskTolerance => $this->withRiskTolerance($value, $policyAllowsElevatedRisk),
            BehaviorTraitAxis::PriorityStrategy => $this->withPriorityStrategy($value),
            BehaviorTraitAxis::DelegationStrategy => $this->withDelegationStrategy($value),
            BehaviorTraitAxis::PlanningStrategy => $this->withPlanningStrategy($value),
            BehaviorTraitAxis::FollowUpStrategy => $this->withFollowUpStrategy($value),
            BehaviorTraitAxis::DocumentationStyle => $this->withDocumentationStyle($value),
            BehaviorTraitAxis::MeetingStyle => $this->withMeetingStyle($value),
            BehaviorTraitAxis::NegotiationStyle => $this->withNegotiationStyle($value),
            BehaviorTraitAxis::CustomerInteractionStyle => $this->withCustomerInteractionStyle($value),
            BehaviorTraitAxis::QualityExpectation => $this->withQualityExpectation($value),
        };
    }

    /**
     * The per-trait differences between this set and another.
     *
     * The returned map is keyed by trait name; each entry holds the string value of this set (`from`)
     * and of the other set (`to`) for every trait whose value differs. An empty map means the two
     * sets are equal. This is the explainable basis for change logs and diffs.
     *
     * @return array<string, array{from: string, to: string}>
     */
    public function diff(self $other): array
    {
        $mine = $this->toArray();
        $theirs = $other->toArray();

        $diff = [];
        foreach ($mine as $trait => $value) {
            $otherValue = $theirs[$trait];
            if ($value !== $otherValue) {
                $diff[$trait] = ['from' => (string) $value, 'to' => (string) $otherValue];
            }
        }

        return $diff;
    }

    /**
     * Structural equality across all fourteen traits and the evidence requirement.
     */
    public function equals(ValueObject $other): bool
    {
        return $other instanceof self && $this->toArray() === $other->toArray();
    }

    /**
     * A scalar-only representation suitable for JSON persistence, diffing, and read models.
     *
     * @return array{
     *     decisionStyle: string,
     *     communicationStyle: string,
     *     approvalStyle: string,
     *     escalationStyle: string,
     *     riskTolerance: string,
     *     priorityStrategy: string,
     *     delegationStrategy: string,
     *     planningStrategy: string,
     *     followUpStrategy: string,
     *     documentationStyle: string,
     *     meetingStyle: string,
     *     negotiationStyle: string,
     *     customerInteractionStyle: string,
     *     qualityExpectation: string,
     *     evidenceRequirements: int
     * }
     */
    public function toArray(): array
    {
        return [
            'decisionStyle' => $this->decisionStyle->value,
            'communicationStyle' => $this->communicationStyle->value,
            'approvalStyle' => $this->approvalStyle->value,
            'escalationStyle' => $this->escalationStyle->value,
            'riskTolerance' => $this->riskTolerance->value,
            'priorityStrategy' => $this->priorityStrategy->value,
            'delegationStrategy' => $this->delegationStrategy->value,
            'planningStrategy' => $this->planningStrategy->value,
            'followUpStrategy' => $this->followUpStrategy->value,
            'documentationStyle' => $this->documentationStyle->value,
            'meetingStyle' => $this->meetingStyle->value,
            'negotiationStyle' => $this->negotiationStyle->value,
            'customerInteractionStyle' => $this->customerInteractionStyle->value,
            'qualityExpectation' => $this->qualityExpectation->value,
            'evidenceRequirements' => $this->evidenceRequirements,
        ];
    }
}
