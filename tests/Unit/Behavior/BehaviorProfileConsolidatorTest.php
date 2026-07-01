<?php

declare(strict_types=1);

namespace Nizam\Tests\Unit\Behavior;

use DateTimeImmutable;
use Nizam\Behavior\Domain\BehaviorObservation;
use Nizam\Behavior\Domain\Enum\BehaviorTraitAxis;
use Nizam\Behavior\Domain\Enum\CommunicationStyle;
use Nizam\Behavior\Domain\Enum\DecisionStyle;
use Nizam\Behavior\Domain\Enum\RiskTolerance;
use Nizam\Behavior\Domain\ObservationId;
use Nizam\Behavior\Domain\RoleId;
use Nizam\Behavior\Domain\Service\BehaviorProfileConsolidator;
use Nizam\Behavior\Domain\ValueObject\EvidenceReference;
use Nizam\Kernel\Domain\TenantId;
use Nizam\Platform\Exception\InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(BehaviorProfileConsolidator::class)]
final class BehaviorProfileConsolidatorTest extends TestCase
{
    use BehaviorFixtures;

    private BehaviorProfileConsolidator $consolidator;
    private RoleId $roleId;
    private TenantId $tenantId;

    protected function setUp(): void
    {
        $this->consolidator = new BehaviorProfileConsolidator();
        $this->roleId = RoleId::generate();
        $this->tenantId = TenantId::generate();
    }

    /**
     * The role-vs-person rule: a single consolidated profile is derived from the approved practice of
     * MULTIPLE employees, not from any one person.
     */
    public function testConsolidatesApprovedObservationsFromMultipleEmployeesIntoOneRoleProfile(): void
    {
        // Three different employees each contribute an approved, distinct piece of practice.
        $fromAlice = $this->approvedObservation($this->roleId, $this->tenantId, 'alice-decision', 1.0);
        $fromBob = $this->approvedObservation($this->roleId, $this->tenantId, 'bob-decision', 1.0);
        $fromCarol = $this->approvedObservation($this->roleId, $this->tenantId, 'carol-decision', 1.0);

        $current = $this->traits(evidenceRequirements: 1);

        $consolidated = $this->consolidator->consolidate(
            $this->roleId,
            [$fromAlice, $fromBob, $fromCarol],
            $current,
        );

        // Three distinct approved practices (total weight 3.0) raise the evidence bar to 3.
        self::assertSame(3, $consolidated->evidenceRequirements());
        // The style axes remain anchored to the role's already-approved baseline.
        self::assertTrue(
            $consolidated->withEvidenceRequirements(1)->equals($current->withEvidenceRequirements(1)),
        );
    }

    /**
     * The engine's core purpose: a style axis is derived from approved practice, not left frozen. A
     * majority of approved observations attesting a different decision style moves that axis.
     */
    public function testWeightedMajorityAcrossEmployeesDerivesAStyleAxisValue(): void
    {
        // Baseline decision style is DataDriven; two of three approved practices attest Consultative.
        $current = $this->traits(1); // decisionStyle => DataDriven
        $observations = [
            $this->approvedObservation($this->roleId, $this->tenantId, 'a', 1.0, observedTraits: [
                BehaviorTraitAxis::DecisionStyle->value => DecisionStyle::Consultative->value,
            ]),
            $this->approvedObservation($this->roleId, $this->tenantId, 'b', 1.0, observedTraits: [
                BehaviorTraitAxis::DecisionStyle->value => DecisionStyle::Consultative->value,
            ]),
            $this->approvedObservation($this->roleId, $this->tenantId, 'c', 1.0, observedTraits: [
                BehaviorTraitAxis::DecisionStyle->value => DecisionStyle::Directive->value,
            ]),
        ];

        $consolidated = $this->consolidator->consolidate($this->roleId, $observations, $current);

        // The winning vote (Consultative, weight 2.0) overrides the baseline (DataDriven).
        self::assertSame(DecisionStyle::Consultative, $consolidated->decisionStyle());
        // An axis nobody voted on stays anchored to the baseline.
        self::assertSame($current->communicationStyle(), $consolidated->communicationStyle());
    }

    /**
     * Weighting matters: a single heavy piece of approved practice outvotes several light ones.
     */
    public function testHeavierEvidenceOutvotesMoreNumerousLighterEvidence(): void
    {
        $current = $this->traits(1); // communicationStyle => Concise
        $observations = [
            $this->approvedObservation($this->roleId, $this->tenantId, 'light-1', 0.2, observedTraits: [
                BehaviorTraitAxis::CommunicationStyle->value => CommunicationStyle::Formal->value,
            ]),
            $this->approvedObservation($this->roleId, $this->tenantId, 'light-2', 0.2, observedTraits: [
                BehaviorTraitAxis::CommunicationStyle->value => CommunicationStyle::Formal->value,
            ]),
            $this->approvedObservation($this->roleId, $this->tenantId, 'heavy', 1.0, observedTraits: [
                BehaviorTraitAxis::CommunicationStyle->value => CommunicationStyle::Detailed->value,
            ]),
        ];

        $consolidated = $this->consolidator->consolidate($this->roleId, $observations, $current);

        // Detailed (weight 1.0) beats Formal (weight 0.4).
        self::assertSame(CommunicationStyle::Detailed, $consolidated->communicationStyle());
    }

    /**
     * A tied vote leaves the axis at the baseline: the engine only moves an axis when practice clearly
     * justifies it.
     */
    public function testATiedVoteLeavesTheAxisAtTheBaseline(): void
    {
        $current = $this->traits(1); // decisionStyle => DataDriven
        $observations = [
            $this->approvedObservation($this->roleId, $this->tenantId, 'a', 1.0, observedTraits: [
                BehaviorTraitAxis::DecisionStyle->value => DecisionStyle::Consultative->value,
            ]),
            $this->approvedObservation($this->roleId, $this->tenantId, 'b', 1.0, observedTraits: [
                BehaviorTraitAxis::DecisionStyle->value => DecisionStyle::Directive->value,
            ]),
        ];

        $consolidated = $this->consolidator->consolidate($this->roleId, $observations, $current);

        self::assertSame(DecisionStyle::DataDriven, $consolidated->decisionStyle());
    }

    /**
     * Even a unanimous vote for elevated risk cannot elevate a role here: the consolidator has no
     * policy authority and clamps the winning value down.
     */
    public function testAWinningElevatedRiskVoteIsClampedToBalanced(): void
    {
        $observations = [
            $this->approvedObservation($this->roleId, $this->tenantId, 'a', 1.0, observedTraits: [
                BehaviorTraitAxis::RiskTolerance->value => RiskTolerance::Elevated->value,
            ]),
            $this->approvedObservation($this->roleId, $this->tenantId, 'b', 1.0, observedTraits: [
                BehaviorTraitAxis::RiskTolerance->value => RiskTolerance::Elevated->value,
            ]),
        ];

        $consolidated = $this->consolidator->consolidate($this->roleId, $observations, $this->traits(1));

        self::assertSame(RiskTolerance::Balanced, $consolidated->riskTolerance());
    }

    public function testEvidenceBarIsCappedRegardlessOfCorroboration(): void
    {
        $observations = [];
        for ($i = 0; $i < 12; $i++) {
            $observations[] = $this->approvedObservation($this->roleId, $this->tenantId, 'ref-' . $i, 1.0);
        }

        $consolidated = $this->consolidator->consolidate($this->roleId, $observations, $this->traits(1));

        // Capped at MAX_EVIDENCE_REQUIREMENT (5) even with 12 corroborating practices.
        self::assertSame(5, $consolidated->evidenceRequirements());
    }

    public function testConsolidationIsDeterministicForTheSameInputs(): void
    {
        $observations = [
            $this->approvedObservation($this->roleId, $this->tenantId, 'a', 0.9),
            $this->approvedObservation($this->roleId, $this->tenantId, 'b', 0.8),
        ];

        $first = $this->consolidator->consolidate($this->roleId, $observations, $this->traits(1));
        $second = $this->consolidator->consolidate($this->roleId, $observations, $this->traits(1));

        self::assertTrue($first->equals($second));
    }

    public function testConsolidatorNeverEmitsElevatedRiskEvenWhenBaselineIsElevated(): void
    {
        // A baseline that (via policy) already carries elevated risk.
        $elevatedBaseline = $this->traits(1)->withRiskTolerance(RiskTolerance::Elevated, policyAllowsElevatedRisk: true);

        $consolidated = $this->consolidator->consolidate(
            $this->roleId,
            [$this->approvedObservation($this->roleId, $this->tenantId, 'a', 1.0)],
            $elevatedBaseline,
        );

        // The consolidator has no policy authority, so it clamps risk down to Balanced.
        self::assertSame(RiskTolerance::Balanced, $consolidated->riskTolerance());
    }

    public function testConsolidationWithoutBaselineFallsBackToSafeConservativeDefaults(): void
    {
        $consolidated = $this->consolidator->consolidate(
            $this->roleId,
            [$this->approvedObservation($this->roleId, $this->tenantId, 'a', 1.0)],
            null,
        );

        // A brand-new role starts from low, non-elevated risk.
        self::assertSame(RiskTolerance::Low, $consolidated->riskTolerance());
        self::assertGreaterThanOrEqual(1, $consolidated->evidenceRequirements());
    }

    public function testConsolidationRejectsUnapprovedObservations(): void
    {
        $unapproved = BehaviorObservation::record(
            ObservationId::generate(),
            $this->tenantId,
            $this->roleId,
            new EvidenceReference(
                sourceType: \Nizam\Behavior\Domain\Enum\ObservationSourceType::TaskExecution,
                referenceId: 'unapproved',
                summary: 'Not yet approved.',
                occurredAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
                weight: 1.0,
            ),
        );

        $this->expectException(InvalidArgumentException::class);
        $this->consolidator->consolidate($this->roleId, [$unapproved], $this->traits(1));
    }

    public function testConsolidationRejectsObservationsBelongingToAnotherRole(): void
    {
        $foreign = $this->approvedObservation(RoleId::generate(), $this->tenantId, 'foreign', 1.0);

        $this->expectException(InvalidArgumentException::class);
        $this->consolidator->consolidate($this->roleId, [$foreign], $this->traits(1));
    }
}
