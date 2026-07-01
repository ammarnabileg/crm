<?php

declare(strict_types=1);

namespace Nizam\Tests\Unit\Behavior;

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
use Nizam\Behavior\Domain\Enum\ProfileStatus;
use Nizam\Behavior\Domain\Enum\ProposalStatus;
use Nizam\Behavior\Domain\Enum\QualityExpectation;
use Nizam\Behavior\Domain\Enum\RiskTolerance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(RiskTolerance::class)]
#[CoversClass(ProfileStatus::class)]
#[CoversClass(ProposalStatus::class)]
#[CoversClass(DecisionStyle::class)]
#[CoversClass(CommunicationStyle::class)]
#[CoversClass(ApprovalStyle::class)]
#[CoversClass(EscalationStyle::class)]
#[CoversClass(PriorityStrategy::class)]
#[CoversClass(DelegationStrategy::class)]
#[CoversClass(PlanningStrategy::class)]
#[CoversClass(FollowUpStrategy::class)]
#[CoversClass(DocumentationStyle::class)]
#[CoversClass(MeetingStyle::class)]
#[CoversClass(NegotiationStyle::class)]
#[CoversClass(CustomerInteractionStyle::class)]
#[CoversClass(QualityExpectation::class)]
#[CoversClass(ObservationSourceType::class)]
final class BehaviorEnumTest extends TestCase
{
    /**
     * @return list<array{class-string, int}>
     */
    public static function enumCardinalities(): array
    {
        return [
            [DecisionStyle::class, 5],
            [CommunicationStyle::class, 5],
            [ApprovalStyle::class, 4],
            [EscalationStyle::class, 3],
            [RiskTolerance::class, 4],
            [PriorityStrategy::class, 4],
            [DelegationStrategy::class, 4],
            [PlanningStrategy::class, 3],
            [FollowUpStrategy::class, 3],
            [DocumentationStyle::class, 3],
            [MeetingStyle::class, 3],
            [NegotiationStyle::class, 3],
            [CustomerInteractionStyle::class, 3],
            [QualityExpectation::class, 3],
            [ProfileStatus::class, 4],
            [ProposalStatus::class, 4],
            [ObservationSourceType::class, 10],
        ];
    }

    /**
     * @param class-string $enumClass
     */
    #[DataProvider('enumCardinalities')]
    public function testEnumHasExpectedCaseCount(string $enumClass, int $expected): void
    {
        self::assertCount($expected, $enumClass::cases());
    }

    /**
     * @return list<array{class-string}>
     */
    public static function backedEnums(): array
    {
        $classes = [];
        foreach (self::enumCardinalities() as [$class]) {
            $classes[] = [$class];
        }

        return $classes;
    }

    /**
     * @param class-string $enumClass
     */
    #[DataProvider('backedEnums')]
    public function testEnumValuesAreUniqueLowercaseStringsAndRoundTripViaFrom(string $enumClass): void
    {
        $values = [];
        foreach ($enumClass::cases() as $case) {
            self::assertIsString($case->value);
            self::assertNotSame('', $case->value);
            self::assertSame($case->value, strtolower($case->value), 'Enum values must be lowercase for stable persistence.');
            // Round-trip: from(value) yields the same case.
            self::assertSame($case, $enumClass::from($case->value));
            $values[] = $case->value;
        }

        self::assertSame(array_values(array_unique($values)), $values, 'Enum values must be unique.');
    }

    public function testRiskToleranceElevatedIsTheOnlyPrivilegedLevel(): void
    {
        self::assertTrue(RiskTolerance::Elevated->requiresElevatedRiskPolicy());
        self::assertFalse(RiskTolerance::Averse->requiresElevatedRiskPolicy());
        self::assertFalse(RiskTolerance::Low->requiresElevatedRiskPolicy());
        self::assertFalse(RiskTolerance::Balanced->requiresElevatedRiskPolicy());
    }

    public function testProfileStatusAcceptsChangesOnlyWhileDraftOrActive(): void
    {
        self::assertTrue(ProfileStatus::Draft->acceptsChanges());
        self::assertTrue(ProfileStatus::Active->acceptsChanges());
        self::assertFalse(ProfileStatus::Superseded->acceptsChanges());
        self::assertFalse(ProfileStatus::Archived->acceptsChanges());
    }

    public function testProposalStatusIsPendingOnlyForPending(): void
    {
        self::assertTrue(ProposalStatus::Pending->isPending());
        self::assertFalse(ProposalStatus::Approved->isPending());
        self::assertFalse(ProposalStatus::Rejected->isPending());
        self::assertFalse(ProposalStatus::Withdrawn->isPending());
    }
}
