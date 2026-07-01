<?php

declare(strict_types=1);

namespace Nizam\Tests\Integration\Behavior;

use DateTimeImmutable;
use Nizam\Behavior\Domain\BehaviorChangeProposal;
use Nizam\Behavior\Domain\BehaviorProfile;
use Nizam\Behavior\Domain\BehaviorProfileId;
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
use Nizam\Behavior\Domain\Enum\ProposalStatus;
use Nizam\Behavior\Domain\Enum\QualityExpectation;
use Nizam\Behavior\Domain\Enum\RiskTolerance;
use Nizam\Behavior\Domain\ProposalId;
use Nizam\Behavior\Domain\RoleId;
use Nizam\Behavior\Domain\ValueObject\BehaviorTraits;
use Nizam\Behavior\Domain\ValueObject\ChangeLogEntry;
use Nizam\Behavior\Domain\ValueObject\EvidenceReference;
use Nizam\Behavior\Infrastructure\Migration\SqliteSchema;
use Nizam\Behavior\Infrastructure\Persistence\Pdo\BehaviorMapper;
use Nizam\Behavior\Infrastructure\Persistence\Pdo\PdoBehaviorChangeProposalRepository;
use Nizam\Behavior\Infrastructure\Persistence\Pdo\PdoBehaviorProfileRepository;
use Nizam\Kernel\Domain\Clock;
use Nizam\Kernel\Domain\TenantId;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PdoBehaviorProfileRepository::class)]
#[CoversClass(PdoBehaviorChangeProposalRepository::class)]
#[CoversClass(BehaviorMapper::class)]
#[CoversClass(SqliteSchema::class)]
final class PdoBehaviorRepositoryTest extends TestCase
{
    private PDO $connection;
    private BehaviorMapper $mapper;
    private PdoBehaviorProfileRepository $profiles;
    private PdoBehaviorChangeProposalRepository $proposals;
    private Clock $clock;

    protected function setUp(): void
    {
        $this->connection = new PDO('sqlite::memory:');
        $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        SqliteSchema::apply($this->connection);

        $this->mapper = new BehaviorMapper();
        $this->profiles = new PdoBehaviorProfileRepository($this->connection, $this->mapper);
        $this->proposals = new PdoBehaviorChangeProposalRepository($this->connection, $this->mapper);
        $this->clock = new class implements Clock {
            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable('2026-06-01T12:00:00+00:00');
            }
        };
    }

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

    private function evidence(string $referenceId): EvidenceReference
    {
        return new EvidenceReference(
            sourceType: ObservationSourceType::ApprovedDecision,
            referenceId: $referenceId,
            summary: 'Approved practice ' . $referenceId,
            occurredAt: new DateTimeImmutable('2026-05-01T00:00:00+00:00'),
            weight: 0.9,
        );
    }

    private function draftProfile(TenantId $tenantId, RoleId $roleId): BehaviorProfile
    {
        $profile = BehaviorProfile::draft(
            BehaviorProfileId::generate(),
            $tenantId,
            $roleId,
            $this->traits(1),
            'founder@nizam.test',
            $this->clock,
        );
        $profile->activate('approver@nizam.test', $this->clock);

        return $profile;
    }

    public function testProfileRoundTripsThroughPdoWithFullFidelity(): void
    {
        $tenantId = TenantId::generate();
        $roleId = RoleId::generate();

        $profile = $this->draftProfile($tenantId, $roleId);

        // Add a real second version so we exercise the revision-history round trip.
        $newTraits = $profile->currentTraits()->withDecisionStyle(DecisionStyle::Directive);
        $profile->applyApprovedChange(
            $newTraits,
            new ChangeLogEntry(
                version: 2,
                changedAt: $this->clock->now(),
                changedBy: 'approver@nizam.test',
                summary: 'Shift to a more directive decision style.',
                traitsDiff: $profile->currentTraits()->diff($newTraits),
                businessImpact: 'Faster decisions under deadline pressure.',
            ),
            [$this->evidence('e1')],
            'approver@nizam.test',
            $this->clock,
        );

        $this->profiles->save($profile);

        $loaded = $this->profiles->ofId($tenantId, $profile->profileId());

        self::assertNotNull($loaded);
        self::assertSame($profile->profileId()->toString(), $loaded->profileId()->toString());
        self::assertTrue($loaded->roleId()->equals($roleId));
        self::assertSame(2, $loaded->currentVersion());
        self::assertCount(2, $loaded->revisions());
        self::assertTrue($loaded->currentTraits()->equals($newTraits));
        self::assertSame(DecisionStyle::Directive, $loaded->currentTraits()->decisionStyle());
        // The first revision's original traits survive the round trip unmutated.
        self::assertSame(DecisionStyle::DataDriven, $loaded->revisionOfVersion(1)->traits()->decisionStyle());
        // Lookup by role returns the same aggregate.
        self::assertNotNull($this->profiles->ofRole($tenantId, $roleId));
        self::assertTrue($this->profiles->existsForRole($tenantId, $roleId));
    }

    public function testSaveUpdatesAnExistingProfileInPlaceRatherThanDuplicating(): void
    {
        $tenantId = TenantId::generate();
        $roleId = RoleId::generate();

        $profile = $this->draftProfile($tenantId, $roleId);
        $this->profiles->save($profile);

        $newTraits = $profile->currentTraits()->withQualityExpectation(QualityExpectation::ZeroDefect);
        $profile->applyApprovedChange(
            $newTraits,
            new ChangeLogEntry(
                version: 2,
                changedAt: $this->clock->now(),
                changedBy: 'approver@nizam.test',
                summary: 'Raise the quality bar.',
                traitsDiff: $profile->currentTraits()->diff($newTraits),
                businessImpact: 'Fewer defects reach customers.',
            ),
            [$this->evidence('e1')],
            'approver@nizam.test',
            $this->clock,
        );
        $this->profiles->save($profile);

        $count = (int) $this->connection
            ->query('SELECT COUNT(*) FROM behavior_profiles')
            ->fetchColumn();
        self::assertSame(1, $count);

        $loaded = $this->profiles->ofId($tenantId, $profile->profileId());
        self::assertNotNull($loaded);
        self::assertSame(2, $loaded->currentVersion());
    }

    public function testTenantIsolationPreventsASecondTenantFromReadingTheFirstsProfile(): void
    {
        $tenantA = TenantId::generate();
        $tenantB = TenantId::generate();
        $roleId = RoleId::generate();

        $profile = $this->draftProfile($tenantA, $roleId);
        $this->profiles->save($profile);

        // Tenant A sees its own profile.
        self::assertNotNull($this->profiles->ofId($tenantA, $profile->profileId()));

        // Tenant B, using the very same profile id, sees nothing.
        self::assertNull($this->profiles->ofId($tenantB, $profile->profileId()));
        self::assertNull($this->profiles->ofRole($tenantB, $roleId));
        self::assertFalse($this->profiles->existsForRole($tenantB, $roleId));
    }

    public function testProposalRoundTripsAndApprovalPersistsTheDecidedStatus(): void
    {
        $tenantId = TenantId::generate();
        $roleId = RoleId::generate();
        $profile = $this->draftProfile($tenantId, $roleId);
        $this->profiles->save($profile);

        $proposal = BehaviorChangeProposal::propose(
            ProposalId::generate(),
            $tenantId,
            $roleId,
            $profile->profileId(),
            $this->traits(3),
            'Approved practice warrants a higher evidence bar.',
            [$this->evidence('e1'), $this->evidence('e2')],
            0.77,
            'Makes future changes to this role harder to push through.',
            'analyst@nizam.test',
            $this->clock,
        );
        $this->proposals->save($proposal);

        // Pending listing sees it.
        $pending = $this->proposals->pendingForTenant($tenantId);
        self::assertCount(1, $pending);
        self::assertSame($proposal->proposalId()->toString(), $pending[0]->proposalId()->toString());
        self::assertTrue($pending[0]->proposedTraits()->equals($this->traits(3)));

        // Approve and persist the decision.
        $reloaded = $this->proposals->ofId($tenantId, $proposal->proposalId());
        self::assertNotNull($reloaded);
        $reloaded->approve('director@nizam.test', $this->clock);
        $this->proposals->save($reloaded);

        // The decided status is persisted.
        $afterApproval = $this->proposals->ofId($tenantId, $proposal->proposalId());
        self::assertNotNull($afterApproval);
        self::assertSame(ProposalStatus::Approved, $afterApproval->status());
        self::assertSame('director@nizam.test', $afterApproval->decidedBy());
        self::assertNotNull($afterApproval->decidedAt());

        // It is no longer pending.
        self::assertSame([], $this->proposals->pendingForTenant($tenantId));
    }

    public function testProposalTenantIsolationPreventsCrossTenantReads(): void
    {
        $tenantA = TenantId::generate();
        $tenantB = TenantId::generate();
        $roleId = RoleId::generate();
        $profile = $this->draftProfile($tenantA, $roleId);
        $this->profiles->save($profile);

        $proposal = BehaviorChangeProposal::propose(
            ProposalId::generate(),
            $tenantA,
            $roleId,
            $profile->profileId(),
            $this->traits(3),
            'Rationale.',
            [$this->evidence('e1')],
            0.6,
            'Impact.',
            'analyst@nizam.test',
            $this->clock,
        );
        $this->proposals->save($proposal);

        self::assertNull($this->proposals->ofId($tenantB, $proposal->proposalId()));
        self::assertSame([], $this->proposals->pendingForTenant($tenantB));
    }

    public function testApplyingAProposalPersistsTheNewProfileVersion(): void
    {
        // The end-to-end approval-gated flow: propose -> approve -> APPLY -> the profile's new
        // version is persisted (nothing applied until the deliberate applyApprovedChange step).
        $tenantId = TenantId::generate();
        $roleId = RoleId::generate();
        $profile = $this->draftProfile($tenantId, $roleId);
        $this->profiles->save($profile);

        $proposedTraits = $profile->currentTraits()->withDecisionStyle(DecisionStyle::Cautious);
        $proposal = BehaviorChangeProposal::propose(
            ProposalId::generate(),
            $tenantId,
            $roleId,
            $profile->profileId(),
            $proposedTraits,
            'Approved practice favors a more cautious decision style.',
            [$this->evidence('e1')],
            0.8,
            'Reduces risk on irreversible decisions.',
            'analyst@nizam.test',
            $this->clock,
        );
        $this->proposals->save($proposal);

        // Approve.
        $proposal->approve('director@nizam.test', $this->clock);
        $this->proposals->save($proposal);

        // Apply the approved proposal to the profile as a new version.
        $target = $this->profiles->ofId($tenantId, $profile->profileId());
        self::assertNotNull($target);
        $target->assertBoundToRole($proposal->roleId());
        $newVersion = $target->currentVersion() + 1;
        $target->applyApprovedChange(
            $proposal->proposedTraits(),
            new ChangeLogEntry(
                version: $newVersion,
                changedAt: $this->clock->now(),
                changedBy: 'director@nizam.test',
                summary: 'Applied approved proposal.',
                traitsDiff: $target->currentTraits()->diff($proposal->proposedTraits()),
                businessImpact: $proposal->businessImpact(),
            ),
            $proposal->supportingEvidence(),
            'director@nizam.test',
            $this->clock,
        );
        $this->profiles->save($target);

        // The persisted profile now carries the applied version and traits.
        $persisted = $this->profiles->ofId($tenantId, $profile->profileId());
        self::assertNotNull($persisted);
        self::assertSame($newVersion, $persisted->currentVersion());
        self::assertSame(DecisionStyle::Cautious, $persisted->currentTraits()->decisionStyle());
        self::assertTrue($persisted->currentTraits()->equals($proposedTraits));
    }
}
