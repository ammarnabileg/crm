<?php

declare(strict_types=1);

namespace Nizam\Tests\Unit\Behavior;

use Nizam\Behavior\Domain\BehaviorProfile;
use Nizam\Behavior\Domain\BehaviorProfileId;
use Nizam\Behavior\Domain\Enum\BehaviorTraitAxis;
use Nizam\Behavior\Domain\Enum\DecisionStyle;
use Nizam\Behavior\Domain\RoleId;
use Nizam\Behavior\Domain\Service\BehaviorProfileConsolidator;
use Nizam\Behavior\Domain\Service\BehaviorRecommendationService;
use Nizam\Behavior\Domain\ValueObject\BehaviorRecommendation;
use Nizam\Behavior\Domain\ValueObject\EvidenceReference;
use Nizam\Kernel\Domain\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(BehaviorRecommendationService::class)]
final class BehaviorRecommendationServiceTest extends TestCase
{
    use BehaviorFixtures;

    private BehaviorRecommendationService $service;
    private RoleId $roleId;
    private TenantId $tenantId;
    private MutableTestClock $clock;

    protected function setUp(): void
    {
        $this->service = new BehaviorRecommendationService(new BehaviorProfileConsolidator());
        $this->roleId = RoleId::generate();
        $this->tenantId = TenantId::generate();
        $this->clock = new MutableTestClock();
    }

    private function profileWithEvidenceRequirement(int $evidenceRequirements): BehaviorProfile
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

    public function testEveryRecommendationIsFullyExplainable(): void
    {
        // Profile currently requires 1 evidence; three distinct approved practices push the supported
        // requirement to 3, so the consolidator's output differs and a recommendation is produced.
        $profile = $this->profileWithEvidenceRequirement(1);
        $approved = [
            $this->approvedObservation($this->roleId, $this->tenantId, 'a', 0.9),
            $this->approvedObservation($this->roleId, $this->tenantId, 'b', 0.8),
            $this->approvedObservation($this->roleId, $this->tenantId, 'c', 0.7),
        ];

        $recommendations = $this->service->recommend($profile, $approved);

        self::assertNotEmpty($recommendations);
        foreach ($recommendations as $recommendation) {
            self::assertInstanceOf(BehaviorRecommendation::class, $recommendation);

            // reason present.
            self::assertNotSame('', $recommendation->reason());
            // non-empty supporting evidence, each a real EvidenceReference.
            self::assertNotEmpty($recommendation->supportingEvidence());
            foreach ($recommendation->supportingEvidence() as $evidence) {
                self::assertInstanceOf(EvidenceReference::class, $evidence);
            }
            // confidence within [0, 1].
            self::assertGreaterThanOrEqual(0.0, $recommendation->confidence());
            self::assertLessThanOrEqual(1.0, $recommendation->confidence());
            // howToModify present and actionable.
            self::assertNotSame('', $recommendation->howToModify());
            // The recommendation actually changes the value.
            self::assertNotSame($recommendation->currentValue(), $recommendation->recommendedValue());
        }
    }

    /**
     * The engine must be able to recommend a change to an actual behavior STYLE axis — not merely the
     * evidence bar — when approved practice supports it.
     */
    public function testRecommendsAStyleAxisChangeWhenApprovedPracticeSupportsIt(): void
    {
        // Profile decides DataDriven; a majority of approved practice attests Consultative.
        $profile = $this->profileWithEvidenceRequirement(1);
        $approved = [
            $this->approvedObservation($this->roleId, $this->tenantId, 'a', 0.9, observedTraits: [
                BehaviorTraitAxis::DecisionStyle->value => DecisionStyle::Consultative->value,
            ]),
            $this->approvedObservation($this->roleId, $this->tenantId, 'b', 0.9, observedTraits: [
                BehaviorTraitAxis::DecisionStyle->value => DecisionStyle::Consultative->value,
            ]),
        ];

        $recommendations = $this->service->recommend($profile, $approved);

        $byTrait = [];
        foreach ($recommendations as $recommendation) {
            $byTrait[$recommendation->targetTrait()] = $recommendation;
        }

        self::assertArrayHasKey(BehaviorTraitAxis::DecisionStyle->value, $byTrait);
        $decision = $byTrait[BehaviorTraitAxis::DecisionStyle->value];
        self::assertSame(DecisionStyle::DataDriven->value, $decision->currentValue());
        self::assertSame(DecisionStyle::Consultative->value, $decision->recommendedValue());
        self::assertNotEmpty($decision->supportingEvidence());
    }

    public function testConfidenceReflectsAverageEvidenceWeight(): void
    {
        $profile = $this->profileWithEvidenceRequirement(1);
        $approved = [
            $this->approvedObservation($this->roleId, $this->tenantId, 'a', 0.6),
            $this->approvedObservation($this->roleId, $this->tenantId, 'b', 0.8),
        ];

        $recommendations = $this->service->recommend($profile, $approved);

        self::assertNotEmpty($recommendations);
        // Average of 0.6 and 0.8 is 0.7.
        self::assertEqualsWithDelta(0.7, $recommendations[0]->confidence(), 0.0001);
    }

    public function testNoRecommendationsWhenThereIsNoApprovedEvidence(): void
    {
        $profile = $this->profileWithEvidenceRequirement(1);

        self::assertSame([], $this->service->recommend($profile, []));
    }

    public function testNoRecommendationsWhenCurrentTraitsAlreadyMatchSupportedTraits(): void
    {
        // A single approved practice keeps the derived requirement at 1, matching the current profile,
        // so there is nothing defensible to recommend.
        $profile = $this->profileWithEvidenceRequirement(1);
        $approved = [$this->approvedObservation($this->roleId, $this->tenantId, 'only', 0.5)];

        self::assertSame([], $this->service->recommend($profile, $approved));
    }
}
