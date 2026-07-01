<?php

declare(strict_types=1);

namespace Nizam\Tests\Unit\Behavior;

use Nizam\Behavior\Domain\Enum\DecisionStyle;
use Nizam\Behavior\Domain\Enum\QualityExpectation;
use Nizam\Behavior\Domain\Enum\RiskTolerance;
use Nizam\Behavior\Domain\Exception\ElevatedRiskNotAllowedException;
use Nizam\Behavior\Domain\ValueObject\BehaviorTraits;
use Nizam\Platform\Exception\InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(BehaviorTraits::class)]
final class BehaviorTraitsTest extends TestCase
{
    use BehaviorFixtures;

    public function testEqualIdenticalTraitSetsAreEqual(): void
    {
        self::assertTrue($this->traits()->equals($this->traits()));
    }

    public function testTraitSetsDifferingOnAnyAxisAreNotEqual(): void
    {
        $a = $this->traits();
        $b = $this->traits()->withDecisionStyle(DecisionStyle::Directive);

        self::assertFalse($a->equals($b));
        self::assertFalse($b->equals($a));
    }

    public function testDifferingEvidenceRequirementBreaksEquality(): void
    {
        $a = $this->traits(1);
        $b = $this->traits(3);

        self::assertFalse($a->equals($b));
    }

    public function testWithMethodsReturnNewImmutableCopyLeavingOriginalUnchanged(): void
    {
        $original = $this->traits();
        $changed = $original->withQualityExpectation(QualityExpectation::ZeroDefect);

        self::assertNotSame($original, $changed);
        // Original is untouched — copy-on-write.
        self::assertSame(QualityExpectation::High, $original->qualityExpectation());
        self::assertSame(QualityExpectation::ZeroDefect, $changed->qualityExpectation());
    }

    public function testDiffReportsOnlyChangedTraitsWithFromAndTo(): void
    {
        $from = $this->traits();
        $to = $this->traits()
            ->withDecisionStyle(DecisionStyle::Cautious)
            ->withQualityExpectation(QualityExpectation::ZeroDefect);

        $diff = $from->diff($to);

        self::assertArrayHasKey('decisionStyle', $diff);
        self::assertArrayHasKey('qualityExpectation', $diff);
        self::assertCount(2, $diff);
        self::assertSame(
            ['from' => DecisionStyle::DataDriven->value, 'to' => DecisionStyle::Cautious->value],
            $diff['decisionStyle'],
        );
        self::assertSame(
            ['from' => QualityExpectation::High->value, 'to' => QualityExpectation::ZeroDefect->value],
            $diff['qualityExpectation'],
        );
    }

    public function testDiffOfEqualTraitSetsIsEmpty(): void
    {
        self::assertSame([], $this->traits()->diff($this->traits()));
    }

    public function testCreateForbidsElevatedRiskWithoutPolicyAllowance(): void
    {
        $this->expectException(ElevatedRiskNotAllowedException::class);

        BehaviorTraits::create(
            decisionStyle: DecisionStyle::DataDriven,
            communicationStyle: \Nizam\Behavior\Domain\Enum\CommunicationStyle::Concise,
            approvalStyle: \Nizam\Behavior\Domain\Enum\ApprovalStyle::SingleApprover,
            escalationStyle: \Nizam\Behavior\Domain\Enum\EscalationStyle::OnThreshold,
            riskTolerance: RiskTolerance::Elevated,
            priorityStrategy: \Nizam\Behavior\Domain\Enum\PriorityStrategy::DeadlineFirst,
            delegationStrategy: \Nizam\Behavior\Domain\Enum\DelegationStrategy::DelegateWithReview,
            planningStrategy: \Nizam\Behavior\Domain\Enum\PlanningStrategy::Structured,
            followUpStrategy: \Nizam\Behavior\Domain\Enum\FollowUpStrategy::Scheduled,
            documentationStyle: \Nizam\Behavior\Domain\Enum\DocumentationStyle::Standard,
            meetingStyle: \Nizam\Behavior\Domain\Enum\MeetingStyle::BriefSync,
            negotiationStyle: \Nizam\Behavior\Domain\Enum\NegotiationStyle::Principled,
            customerInteractionStyle: \Nizam\Behavior\Domain\Enum\CustomerInteractionStyle::Proactive,
            qualityExpectation: QualityExpectation::High,
            evidenceRequirements: 1,
        );
    }

    public function testWithPolicyAllowanceAdmitsElevatedRiskWhenPermitted(): void
    {
        $traits = $this->traits()->withRiskTolerance(RiskTolerance::Elevated, policyAllowsElevatedRisk: true);

        self::assertSame(RiskTolerance::Elevated, $traits->riskTolerance());
    }

    public function testWithRiskToleranceRejectsElevatedRiskByDefault(): void
    {
        $this->expectException(ElevatedRiskNotAllowedException::class);

        $this->traits()->withRiskTolerance(RiskTolerance::Elevated);
    }

    public function testEvidenceRequirementsMustBePositive(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->traits(0);
    }
}
