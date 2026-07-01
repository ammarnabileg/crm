<?php

declare(strict_types=1);

namespace Nizam\Tests\Unit\Behavior;

use Nizam\Behavior\Domain\BehaviorChangeProposal;
use Nizam\Behavior\Domain\BehaviorProfile;
use Nizam\Behavior\Domain\BehaviorProfileId;
use Nizam\Behavior\Domain\Enum\ProposalStatus;
use Nizam\Behavior\Domain\Event\BehaviorChangeApproved;
use Nizam\Behavior\Domain\Event\BehaviorChangeProposed;
use Nizam\Behavior\Domain\Event\BehaviorChangeRejected;
use Nizam\Behavior\Domain\Event\BehaviorChangeWithdrawn;
use Nizam\Behavior\Domain\Exception\InvalidProposalTransitionException;
use Nizam\Behavior\Domain\ProposalId;
use Nizam\Behavior\Domain\RoleId;
use Nizam\Kernel\Domain\TenantId;
use Nizam\Platform\Exception\InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(BehaviorChangeProposal::class)]
final class BehaviorChangeProposalTest extends TestCase
{
    use BehaviorFixtures;

    private TenantId $tenantId;
    private RoleId $roleId;
    private BehaviorProfileId $profileId;
    private MutableTestClock $clock;

    protected function setUp(): void
    {
        $this->tenantId = TenantId::generate();
        $this->roleId = RoleId::generate();
        $this->profileId = BehaviorProfileId::generate();
        $this->clock = new MutableTestClock();
    }

    private function propose(): BehaviorChangeProposal
    {
        return BehaviorChangeProposal::propose(
            ProposalId::generate(),
            $this->tenantId,
            $this->roleId,
            $this->profileId,
            $this->traits(2),
            'Approved practice supports a stricter evidence bar.',
            [$this->evidence('e1'), $this->evidence('e2')],
            0.82,
            'Raises the evidence requirement for this role.',
            'analyst@nizam.test',
            $this->clock,
        );
    }

    public function testProposeCreatesPendingProposalAndEmitsProposedEvent(): void
    {
        $proposal = $this->propose();

        self::assertSame(ProposalStatus::Pending, $proposal->status());
        self::assertNull($proposal->decidedBy());
        self::assertNull($proposal->decidedAt());

        $events = $proposal->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(BehaviorChangeProposed::class, $events[0]);
    }

    public function testApproveMarksApprovedAndEmitsApprovedEventButChangesNoProfile(): void
    {
        $proposal = $this->propose();
        $proposal->pullDomainEvents();

        // A separate, untouched target profile — approving the proposal must not mutate it.
        $profile = BehaviorProfile::draft(
            $this->profileId,
            $this->tenantId,
            $this->roleId,
            $this->traits(1),
            'founder@nizam.test',
            $this->clock,
        );
        $versionBefore = $profile->currentVersion();
        $traitsBefore = $profile->currentTraits();

        $this->clock->advance(30);
        $proposal->approve('director@nizam.test', $this->clock);

        self::assertSame(ProposalStatus::Approved, $proposal->status());
        self::assertSame('director@nizam.test', $proposal->decidedBy());
        self::assertNotNull($proposal->decidedAt());

        $events = $proposal->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(BehaviorChangeApproved::class, $events[0]);

        // NOTHING auto-applies: the profile is byte-for-byte unchanged after approval.
        self::assertSame($versionBefore, $profile->currentVersion());
        self::assertTrue($traitsBefore->equals($profile->currentTraits()));
    }

    public function testRejectMarksRejectedAndEmitsRejectedEvent(): void
    {
        $proposal = $this->propose();
        $proposal->pullDomainEvents();

        $proposal->reject('Insufficient business justification.', 'director@nizam.test', $this->clock);

        self::assertSame(ProposalStatus::Rejected, $proposal->status());
        self::assertSame('director@nizam.test', $proposal->decidedBy());

        $events = $proposal->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(BehaviorChangeRejected::class, $events[0]);
    }

    public function testWithdrawMarksWithdrawnAndEmitsWithdrawnEvent(): void
    {
        $proposal = $this->propose();
        $proposal->pullDomainEvents();

        $proposal->withdraw('analyst@nizam.test', $this->clock);

        self::assertSame(ProposalStatus::Withdrawn, $proposal->status());

        $events = $proposal->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(BehaviorChangeWithdrawn::class, $events[0]);
    }

    public function testAnApprovedProposalCannotBeApprovedAgain(): void
    {
        $proposal = $this->propose();
        $proposal->approve('director@nizam.test', $this->clock);

        $this->expectException(InvalidProposalTransitionException::class);
        $proposal->approve('director@nizam.test', $this->clock);
    }

    public function testAnApprovedProposalCannotThenBeRejected(): void
    {
        $proposal = $this->propose();
        $proposal->approve('director@nizam.test', $this->clock);

        $this->expectException(InvalidProposalTransitionException::class);
        $proposal->reject('Changed my mind.', 'director@nizam.test', $this->clock);
    }

    public function testRejectRequiresAReason(): void
    {
        $proposal = $this->propose();

        $this->expectException(InvalidArgumentException::class);
        $proposal->reject('', 'director@nizam.test', $this->clock);
    }

    public function testProposeRequiresAtLeastOneSupportingEvidence(): void
    {
        $this->expectException(InvalidArgumentException::class);

        BehaviorChangeProposal::propose(
            ProposalId::generate(),
            $this->tenantId,
            $this->roleId,
            $this->profileId,
            $this->traits(1),
            'A rationale.',
            [], // no evidence
            0.5,
            'Impact.',
            'analyst@nizam.test',
            $this->clock,
        );
    }

    public function testProposeRejectsConfidenceOutOfRange(): void
    {
        $this->expectException(InvalidArgumentException::class);

        BehaviorChangeProposal::propose(
            ProposalId::generate(),
            $this->tenantId,
            $this->roleId,
            $this->profileId,
            $this->traits(1),
            'A rationale.',
            [$this->evidence('e1')],
            1.5, // out of [0, 1]
            'Impact.',
            'analyst@nizam.test',
            $this->clock,
        );
    }
}
