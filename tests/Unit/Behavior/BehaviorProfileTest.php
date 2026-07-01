<?php

declare(strict_types=1);

namespace Nizam\Tests\Unit\Behavior;

use Nizam\Behavior\Domain\BehaviorProfile;
use Nizam\Behavior\Domain\BehaviorProfileId;
use Nizam\Behavior\Domain\Enum\DecisionStyle;
use Nizam\Behavior\Domain\Enum\ProfileStatus;
use Nizam\Behavior\Domain\Enum\QualityExpectation;
use Nizam\Behavior\Domain\Event\BehaviorProfileActivated;
use Nizam\Behavior\Domain\Event\BehaviorProfileArchived;
use Nizam\Behavior\Domain\Event\BehaviorProfileDrafted;
use Nizam\Behavior\Domain\Event\BehaviorProfileRolledBack;
use Nizam\Behavior\Domain\Event\BehaviorProfileVersionActivated;
use Nizam\Behavior\Domain\Exception\ImmutableRoleBindingException;
use Nizam\Behavior\Domain\Exception\InsufficientEvidenceException;
use Nizam\Behavior\Domain\Exception\InvalidProfileTransitionException;
use Nizam\Behavior\Domain\Exception\NoBehaviorChangeException;
use Nizam\Behavior\Domain\Exception\UnknownRevisionException;
use Nizam\Behavior\Domain\RoleId;
use Nizam\Behavior\Domain\ValueObject\BehaviorTraits;
use Nizam\Behavior\Domain\ValueObject\ChangeLogEntry;
use Nizam\Kernel\Domain\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(BehaviorProfile::class)]
final class BehaviorProfileTest extends TestCase
{
    use BehaviorFixtures;

    private TenantId $tenantId;
    private RoleId $roleId;
    private MutableTestClock $clock;

    protected function setUp(): void
    {
        $this->tenantId = TenantId::generate();
        $this->roleId = RoleId::generate();
        $this->clock = new MutableTestClock();
    }

    private function draftProfile(int $evidenceRequirements = 1): BehaviorProfile
    {
        return BehaviorProfile::draft(
            BehaviorProfileId::generate(),
            $this->tenantId,
            $this->roleId,
            $this->traits($evidenceRequirements),
            'founder@nizam.test',
            $this->clock,
        );
    }

    /**
     * @param BehaviorTraits $newTraits
     */
    private function changeLogFor(int $version, BehaviorTraits $from, BehaviorTraits $newTraits): ChangeLogEntry
    {
        return new ChangeLogEntry(
            version: $version,
            changedAt: $this->clock->now(),
            changedBy: 'approver@nizam.test',
            summary: 'Approved behavior change.',
            traitsDiff: $from->diff($newTraits),
            businessImpact: 'Aligns behavior with approved practice.',
            rollbackToVersion: null,
        );
    }

    public function testDraftStartsAtVersionOneInDraftStatusAndEmitsDraftedEvent(): void
    {
        $profile = $this->draftProfile();

        self::assertSame(ProfileStatus::Draft, $profile->status());
        self::assertSame(1, $profile->currentVersion());
        self::assertCount(1, $profile->revisions());
        self::assertTrue($profile->roleId()->equals($this->roleId));

        $events = $profile->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(BehaviorProfileDrafted::class, $events[0]);
    }

    public function testActivateMovesDraftToActiveAndEmitsActivatedEvent(): void
    {
        $profile = $this->draftProfile();
        $profile->pullDomainEvents();

        $profile->activate('approver@nizam.test', $this->clock);

        self::assertSame(ProfileStatus::Active, $profile->status());
        $events = $profile->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(BehaviorProfileActivated::class, $events[0]);
    }

    public function testActivateOnNonDraftProfileThrows(): void
    {
        $profile = $this->draftProfile();
        $profile->activate('approver@nizam.test', $this->clock);

        $this->expectException(InvalidProfileTransitionException::class);
        $profile->activate('approver@nizam.test', $this->clock);
    }

    public function testApplyApprovedChangeIncrementsVersionAppendsRevisionAndEmitsVersionActivated(): void
    {
        $profile = $this->draftProfile(evidenceRequirements: 2);
        $profile->activate('approver@nizam.test', $this->clock);
        $profile->pullDomainEvents();

        $newTraits = $profile->currentTraits()->withDecisionStyle(DecisionStyle::Directive);
        $changeLog = $this->changeLogFor(2, $profile->currentTraits(), $newTraits);

        $profile->applyApprovedChange(
            $newTraits,
            $changeLog,
            [$this->evidence('e1'), $this->evidence('e2')],
            'approver@nizam.test',
            $this->clock,
        );

        self::assertSame(2, $profile->currentVersion());
        self::assertCount(2, $profile->revisions());
        self::assertTrue($profile->currentTraits()->equals($newTraits));
        self::assertSame(DecisionStyle::Directive, $profile->currentTraits()->decisionStyle());

        $events = $profile->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(BehaviorProfileVersionActivated::class, $events[0]);

        // The first revision is a permanent, unmutated record of version 1.
        self::assertSame(1, $profile->revisions()[0]->version());
        self::assertSame(DecisionStyle::DataDriven, $profile->revisions()[0]->traits()->decisionStyle());
    }

    public function testApplyApprovedChangeWithInsufficientEvidenceThrows(): void
    {
        $profile = $this->draftProfile(evidenceRequirements: 3);
        $profile->activate('approver@nizam.test', $this->clock);

        $newTraits = $profile->currentTraits()->withDecisionStyle(DecisionStyle::Directive);
        $changeLog = $this->changeLogFor(2, $profile->currentTraits(), $newTraits);

        $this->expectException(InsufficientEvidenceException::class);
        $profile->applyApprovedChange(
            $newTraits,
            $changeLog,
            [$this->evidence('e1'), $this->evidence('e2')], // only 2, need 3
            'approver@nizam.test',
            $this->clock,
        );
    }

    public function testApplyApprovedChangeWithoutActualChangeThrows(): void
    {
        $profile = $this->draftProfile();
        $profile->activate('approver@nizam.test', $this->clock);

        $sameTraits = $profile->currentTraits();
        $changeLog = new ChangeLogEntry(
            version: 2,
            changedAt: $this->clock->now(),
            changedBy: 'approver@nizam.test',
            summary: 'No-op.',
            traitsDiff: [],
            businessImpact: 'None.',
        );

        $this->expectException(NoBehaviorChangeException::class);
        $profile->applyApprovedChange($sameTraits, $changeLog, [$this->evidence('e1')], 'approver@nizam.test', $this->clock);
    }

    public function testRollbackToRestoresEarlierTraitsAsANewVersionAppendOnly(): void
    {
        $profile = $this->draftProfile(evidenceRequirements: 1);
        $profile->activate('approver@nizam.test', $this->clock);
        $v1Traits = $profile->currentTraits();

        // v2: change decision style.
        $v2Traits = $v1Traits->withDecisionStyle(DecisionStyle::Directive);
        $this->clock->advance(60);
        $profile->applyApprovedChange(
            $v2Traits,
            $this->changeLogFor(2, $v1Traits, $v2Traits),
            [$this->evidence('e1')],
            'approver@nizam.test',
            $this->clock,
        );
        $profile->pullDomainEvents();

        // Roll back to v1: append a NEW v3 that restores v1's traits.
        $this->clock->advance(60);
        $profile->rollbackTo(1, 'approver@nizam.test', $this->clock);

        self::assertSame(3, $profile->currentVersion());
        self::assertCount(3, $profile->revisions());
        // Current traits equal version 1's traits (reversibility of behavior).
        self::assertTrue($profile->currentTraits()->equals($v1Traits));
        // History is append-only: v2's revision still holds the Directive traits.
        self::assertSame(DecisionStyle::Directive, $profile->revisionOfVersion(2)->traits()->decisionStyle());
        // The new revision records that it is a rollback to version 1.
        self::assertSame(1, $profile->revisionOfVersion(3)->changeLog()->rollbackToVersion());

        $events = $profile->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(BehaviorProfileRolledBack::class, $events[0]);
    }

    public function testRollbackIsReversibleByRollingForwardAgain(): void
    {
        $profile = $this->draftProfile(evidenceRequirements: 1);
        $profile->activate('approver@nizam.test', $this->clock);
        $v1Traits = $profile->currentTraits();

        $v2Traits = $v1Traits->withQualityExpectation(QualityExpectation::ZeroDefect);
        $this->clock->advance(60);
        $profile->applyApprovedChange(
            $v2Traits,
            $this->changeLogFor(2, $v1Traits, $v2Traits),
            [$this->evidence('e1')],
            'approver@nizam.test',
            $this->clock,
        );

        // Roll back to v1 (=> v3 restores v1).
        $this->clock->advance(60);
        $profile->rollbackTo(1, 'approver@nizam.test', $this->clock);
        self::assertTrue($profile->currentTraits()->equals($v1Traits));

        // Roll "forward" to v2 (=> v4 restores v2). Rollback is fully reversible.
        $this->clock->advance(60);
        $profile->rollbackTo(2, 'approver@nizam.test', $this->clock);

        self::assertSame(4, $profile->currentVersion());
        self::assertTrue($profile->currentTraits()->equals($v2Traits));
        self::assertSame(QualityExpectation::ZeroDefect, $profile->currentTraits()->qualityExpectation());
    }

    public function testRollbackToUnknownVersionThrows(): void
    {
        $profile = $this->draftProfile();
        $profile->activate('approver@nizam.test', $this->clock);

        $this->expectException(UnknownRevisionException::class);
        $profile->rollbackTo(99, 'approver@nizam.test', $this->clock);
    }

    public function testRoleBindingIsImmutableAndAssertingAForeignRoleThrows(): void
    {
        $profile = $this->draftProfile();

        // Same role passes.
        $profile->assertBoundToRole($this->roleId);

        // A different role is rejected — behavior is bound per-role forever.
        $this->expectException(ImmutableRoleBindingException::class);
        $profile->assertBoundToRole(RoleId::generate());
    }

    public function testArchiveRetiresProfileAndEmitsArchivedEvent(): void
    {
        $profile = $this->draftProfile();
        $profile->activate('approver@nizam.test', $this->clock);
        $profile->pullDomainEvents();

        $profile->archive('admin@nizam.test', $this->clock);

        self::assertSame(ProfileStatus::Archived, $profile->status());
        $events = $profile->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(BehaviorProfileArchived::class, $events[0]);
    }

    public function testArchivedProfileRejectsFurtherChanges(): void
    {
        $profile = $this->draftProfile();
        $profile->activate('approver@nizam.test', $this->clock);
        $profile->archive('admin@nizam.test', $this->clock);

        $newTraits = $profile->currentTraits()->withDecisionStyle(DecisionStyle::Directive);

        $this->expectException(InvalidProfileTransitionException::class);
        $profile->applyApprovedChange(
            $newTraits,
            $this->changeLogFor(2, $profile->currentTraits(), $newTraits),
            [$this->evidence('e1')],
            'approver@nizam.test',
            $this->clock,
        );
    }
}
