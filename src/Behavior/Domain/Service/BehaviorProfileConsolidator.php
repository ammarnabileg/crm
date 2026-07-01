<?php

declare(strict_types=1);

namespace Nizam\Behavior\Domain\Service;

use Nizam\Behavior\Domain\BehaviorObservation;
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
use Nizam\Behavior\Domain\RoleId;
use Nizam\Behavior\Domain\ValueObject\BehaviorTraits;
use Nizam\Behavior\Domain\ValueObject\EvidenceReference;
use Nizam\Platform\Support\Assert;

/**
 * Derives a single, consolidated behavior profile for a ROLE from the approved practice of the
 * PEOPLE performing it — the engine's role-vs-person rule made concrete.
 *
 * Behavior belongs to the role, not to any individual. This stateless domain service takes the
 * approved observations gathered across every employee in a role and folds them into one
 * {@see BehaviorTraits}. It is deterministic (the same inputs always yield the same output) and
 * explainable: the consolidation rules are simple, total, and inspectable.
 *
 * How it consolidates:
 *  - Only approved observations count; any unapproved input is rejected, because unapproved practice
 *    must never shape a role's behavior.
 *  - Each of the fourteen style axes is derived by WEIGHTED MAJORITY VOTE across the approved
 *    practice: every observation whose evidence attests a value for an axis casts a vote weighted by
 *    that evidence's normalized weight, votes are tallied per candidate value, and the highest-weighted
 *    value wins. Ties, and axes on which no approved practice speaks, fall back to the supplied
 *    baseline (or the conservative default when a role has no baseline yet) — so the engine only moves
 *    an axis when approved practice actually justifies it, and never invents a value from nothing. The
 *    vote order is fixed (axis registry order, then enum case order) so the result is deterministic
 *    and explainable.
 *  - The breadth and strength of corroboration — how many distinct approved practices exist and how
 *    strongly each is weighted — set the profile's `evidenceRequirements`: the more independent
 *    approved evidence a role's behavior rests on, the higher (within a sane cap) the bar future
 *    changes must clear, so a well-established role is not cheaply re-shaped by thin evidence.
 *  - Absent a baseline the consolidator starts axes from a conservative default so a new role begins
 *    from safe, low-risk ground rather than from nothing.
 *  - Risk tolerance is never elevated here: this service has no policy authority, so even a winning
 *    elevated vote is clamped to at most {@see RiskTolerance::Balanced}, leaving any elevation to the
 *    policy-gated path.
 */
final class BehaviorProfileConsolidator
{
    /**
     * The smallest evidence bar a consolidated profile may carry.
     */
    private const int MIN_EVIDENCE_REQUIREMENT = 1;

    /**
     * The largest evidence bar the consolidator will set, regardless of corroboration.
     */
    private const int MAX_EVIDENCE_REQUIREMENT = 5;

    /**
     * The total approved evidence weight that raises the bar by one step.
     */
    private const float WEIGHT_PER_EVIDENCE_STEP = 1.0;

    /**
     * Fold approved observations across all employees in a role into one consolidated trait set.
     *
     * @param RoleId                    $roleId                 The role being consolidated (must match every observation).
     * @param list<BehaviorObservation> $approvedAcrossEmployees Approved observations from all employees in the role.
     * @param BehaviorTraits|null       $current                The role's current traits, when one already exists.
     *
     * @return BehaviorTraits The consolidated role profile.
     */
    public function consolidate(
        RoleId $roleId,
        array $approvedAcrossEmployees,
        ?BehaviorTraits $current = null,
    ): BehaviorTraits {
        $totalWeight = 0.0;
        $distinctPractices = [];
        $evidenceList = [];

        foreach ($approvedAcrossEmployees as $observation) {
            Assert::that(
                $observation instanceof BehaviorObservation,
                'Consolidation input must contain only BehaviorObservation instances.',
            );
            Assert::that(
                $observation->isApproved(),
                'Consolidation may only draw on approved observations.',
            );
            Assert::that(
                $observation->roleId()->equals($roleId),
                'Every observation must belong to the role being consolidated.',
            );

            $evidence = $observation->evidence();
            $totalWeight += $evidence->weight();
            $distinctPractices[$evidence->referenceId()] = true;
            $evidenceList[] = $evidence;
        }

        $baseline = $current ?? $this->defaultBaseline();
        $evidenceRequirements = $this->deriveEvidenceRequirements(
            count($distinctPractices),
            $totalWeight,
        );

        $voted = $this->deriveStyleAxes($baseline, $evidenceList);

        return $voted
            ->withRiskTolerance($this->clampRisk($voted->riskTolerance()))
            ->withEvidenceRequirements($evidenceRequirements);
    }

    /**
     * Derive the fourteen style axes from approved practice by weighted majority vote per axis.
     *
     * For each axis, sum the weight of every evidence attesting each candidate value; the
     * highest-weighted value wins and is applied over the baseline. Axes with no votes, and ties,
     * keep the baseline value — the engine only moves an axis when approved practice justifies it.
     *
     * @param BehaviorTraits         $baseline The starting point (current traits or conservative default).
     * @param list<EvidenceReference> $evidence Approved evidence carrying the per-axis signal.
     *
     * @return BehaviorTraits The baseline with each voted-upon axis updated.
     */
    private function deriveStyleAxes(BehaviorTraits $baseline, array $evidence): BehaviorTraits
    {
        $traits = $baseline;

        foreach (BehaviorTraitAxis::cases() as $axis) {
            $winner = $this->winningValueFor($axis, $evidence);
            if ($winner === null) {
                continue;
            }

            // Elevated risk is never applied here; the final clamp in consolidate() also guards this,
            // and passing policyAllowsElevatedRisk=false keeps withAxis() from throwing on a winner.
            if ($axis === BehaviorTraitAxis::RiskTolerance && $winner === RiskTolerance::Elevated) {
                continue;
            }

            $traits = $traits->withAxis($axis, $winner);
        }

        return $traits;
    }

    /**
     * The weighted-majority winning enum value for one axis, or null when no practice speaks or the
     * vote ties.
     *
     * Weight is tallied per candidate in enum case order; a strictly-greater tally is required to
     * win, so a tie deterministically yields null (leaving the baseline in place).
     *
     * @param list<EvidenceReference> $evidence
     */
    private function winningValueFor(BehaviorTraitAxis $axis, array $evidence): ?\BackedEnum
    {
        /** @var array<string, float> $tally */
        $tally = [];
        foreach ($evidence as $reference) {
            $value = $reference->observedValueFor($axis);
            if ($value === null) {
                continue;
            }

            $tally[$value] = ($tally[$value] ?? 0.0) + $reference->weight();
        }

        if ($tally === []) {
            return null;
        }

        $bestValue = null;
        $bestWeight = 0.0;
        $tied = false;

        $enumClass = $axis->enumClass();
        foreach ($enumClass::cases() as $case) {
            $weight = $tally[$case->value] ?? 0.0;
            if ($weight <= 0.0) {
                continue;
            }

            if ($weight > $bestWeight) {
                $bestValue = $case;
                $bestWeight = $weight;
                $tied = false;
            } elseif ($weight === $bestWeight) {
                $tied = true;
            }
        }

        return $tied ? null : $bestValue;
    }

    /**
     * Translate corroboration into an evidence bar, clamped to a sane range.
     *
     * The bar rises with both the number of distinct approved practices and their cumulative weight,
     * but never below {@see self::MIN_EVIDENCE_REQUIREMENT} nor above {@see self::MAX_EVIDENCE_REQUIREMENT}.
     */
    private function deriveEvidenceRequirements(int $distinctPractices, float $totalWeight): int
    {
        $fromWeight = (int) floor($totalWeight / self::WEIGHT_PER_EVIDENCE_STEP);
        $corroboration = max($distinctPractices, $fromWeight);

        $requirement = max(self::MIN_EVIDENCE_REQUIREMENT, $corroboration);

        return min(self::MAX_EVIDENCE_REQUIREMENT, $requirement);
    }

    /**
     * Prevent the consolidator from ever emitting elevated risk, which requires policy authority.
     */
    private function clampRisk(RiskTolerance $risk): RiskTolerance
    {
        return $risk === RiskTolerance::Elevated ? RiskTolerance::Balanced : $risk;
    }

    /**
     * The conservative default trait set used when a role has no existing baseline.
     */
    private function defaultBaseline(): BehaviorTraits
    {
        return BehaviorTraits::create(
            decisionStyle: DecisionStyle::Consultative,
            communicationStyle: CommunicationStyle::Concise,
            approvalStyle: ApprovalStyle::SingleApprover,
            escalationStyle: EscalationStyle::OnThreshold,
            riskTolerance: RiskTolerance::Low,
            priorityStrategy: PriorityStrategy::DeadlineFirst,
            delegationStrategy: DelegationStrategy::DelegateWithReview,
            planningStrategy: PlanningStrategy::Structured,
            followUpStrategy: FollowUpStrategy::Scheduled,
            documentationStyle: DocumentationStyle::Standard,
            meetingStyle: MeetingStyle::BriefSync,
            negotiationStyle: NegotiationStyle::Principled,
            customerInteractionStyle: CustomerInteractionStyle::Proactive,
            qualityExpectation: QualityExpectation::High,
            evidenceRequirements: self::MIN_EVIDENCE_REQUIREMENT,
        );
    }
}
