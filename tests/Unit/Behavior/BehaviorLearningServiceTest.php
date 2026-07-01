<?php

declare(strict_types=1);

namespace Nizam\Tests\Unit\Behavior;

use Nizam\Behavior\Application\Exception\BehaviorApplicationException;
use Nizam\Behavior\Application\Service\BehaviorLearningService;
use Nizam\Behavior\Domain\BehaviorProfile;
use Nizam\Behavior\Domain\BehaviorProfileId;
use Nizam\Behavior\Domain\Enum\ProposalStatus;
use Nizam\Behavior\Domain\Port\BehaviorEventPublisher;
use Nizam\Behavior\Domain\RoleId;
use Nizam\Behavior\Domain\Service\BehaviorProfileConsolidator;
use Nizam\Behavior\Domain\Service\BehaviorRecommendationService;
use Nizam\Behavior\Infrastructure\Persistence\InMemory\InMemoryBehaviorChangeProposalRepository;
use Nizam\Behavior\Infrastructure\Persistence\InMemory\InMemoryBehaviorProfileRepository;
use Nizam\Behavior\Infrastructure\Persistence\InMemory\InMemoryObservationSource;
use Nizam\Kernel\Domain\DomainEvent;
use Nizam\Kernel\Domain\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(BehaviorLearningService::class)]
final class BehaviorLearningServiceTest extends TestCase
{
    use BehaviorFixtures;

    private TenantId $tenantId;
    private RoleId $roleId;
    private MutableTestClock $clock;
    private InMemoryBehaviorProfileRepository $profiles;
    private InMemoryBehaviorChangeProposalRepository $proposals;
    private InMemoryObservationSource $observations;
    private RecordingBehaviorEventPublisher $publisher;
    private BehaviorLearningService $service;

    protected function setUp(): void
    {
        $this->tenantId = TenantId::generate();
        $this->roleId = RoleId::generate();
        $this->clock = new MutableTestClock();

        $this->profiles = new InMemoryBehaviorProfileRepository();
        $this->proposals = new InMemoryBehaviorChangeProposalRepository();
        $this->observations = new InMemoryObservationSource();
        $this->publisher = new RecordingBehaviorEventPublisher();

        $consolidator = new BehaviorProfileConsolidator();
        $this->service = new BehaviorLearningService(
            $this->profiles,
            $this->proposals,
            $this->observations,
            $consolidator,
            new BehaviorRecommendationService($consolidator),
            $this->publisher,
            $this->clock,
        );
    }

    private function seedActiveProfile(int $evidenceRequirements = 1): BehaviorProfile
    {
        $profile = BehaviorProfile::draft(
            BehaviorProfileId::generate(),
            $this->tenantId,
            $this->roleId,
            $this->traits($evidenceRequirements),
            'founder@nizam.test',
            $this->clock,
        );
        $profile->activate('approver@nizam.test', $this->clock);
        $profile->pullDomainEvents();
        $this->profiles->save($profile);

        return $profile;
    }

    public function testLearnProducesAPendingProposalOnlyAndNeverMutatesTheProfile(): void
    {
        $profile = $this->seedActiveProfile(evidenceRequirements: 1);
        $versionBefore = $profile->currentVersion();
        $traitsBefore = $profile->currentTraits();

        // Three distinct approved practices push the supported evidence bar to 3, warranting a change.
        $this->observations->seed([
            $this->approvedObservation($this->roleId, $this->tenantId, 'a', 0.9),
            $this->approvedObservation($this->roleId, $this->tenantId, 'b', 0.8),
            $this->approvedObservation($this->roleId, $this->tenantId, 'c', 0.7),
        ]);

        $view = $this->service->learn(
            $this->tenantId->toString(),
            $this->roleId->toString(),
            'analyst@nizam.test',
        );

        // A single PENDING proposal is created — the engine recommends, it does not apply.
        self::assertSame(ProposalStatus::Pending->value, $view->status);
        self::assertNotEmpty($view->supportingEvidence);
        self::assertSame($this->roleId->toString(), $view->roleId);

        $pending = $this->proposals->pendingForTenant($this->tenantId);
        self::assertCount(1, $pending);
        self::assertSame($view->proposalId, $pending[0]->proposalId()->toString());

        // The proposal event was published.
        self::assertNotEmpty($this->publisher->published);

        // The profile is completely unchanged: version and traits are identical, still no new revision.
        $reloaded = $this->profiles->ofRole($this->tenantId, $this->roleId);
        self::assertNotNull($reloaded);
        self::assertSame($versionBefore, $reloaded->currentVersion());
        self::assertTrue($traitsBefore->equals($reloaded->currentTraits()));
        self::assertCount(1, $reloaded->revisions());
    }

    public function testLearnThrowsWhenNoProfileExistsForTheRole(): void
    {
        $this->observations->seed([
            $this->approvedObservation($this->roleId, $this->tenantId, 'a', 0.9),
        ]);

        $this->expectException(BehaviorApplicationException::class);
        $this->service->learn($this->tenantId->toString(), $this->roleId->toString(), 'analyst@nizam.test');
    }

    public function testLearnThrowsWhenApprovedPracticeWarrantsNoChange(): void
    {
        $this->seedActiveProfile(evidenceRequirements: 1);
        // A single approved practice keeps the derived requirement at 1 — nothing to propose.
        $this->observations->seed([
            $this->approvedObservation($this->roleId, $this->tenantId, 'only', 0.5),
        ]);

        $this->expectException(BehaviorApplicationException::class);
        $this->service->learn($this->tenantId->toString(), $this->roleId->toString(), 'analyst@nizam.test');
    }
}

/**
 * A test {@see BehaviorEventPublisher} that records every published event for assertions.
 */
final class RecordingBehaviorEventPublisher implements BehaviorEventPublisher
{
    /**
     * @var list<DomainEvent> Every event handed to {@see self::publish()}, in order.
     */
    public array $published = [];

    /**
     * @param list<DomainEvent> $events
     */
    public function publish(array $events): void
    {
        foreach ($events as $event) {
            $this->published[] = $event;
        }
    }
}
