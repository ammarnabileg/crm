<?php

declare(strict_types=1);

namespace Nizam\Behavior\Application\Query;

use Nizam\Behavior\Application\Dto\RecommendationView;
use Nizam\Behavior\Application\Exception\BehaviorApplicationException;
use Nizam\Behavior\Domain\BehaviorProfileId;
use Nizam\Behavior\Domain\Port\BehaviorProfileRepository;
use Nizam\Behavior\Domain\Port\ObservationSource;
use Nizam\Behavior\Domain\Service\BehaviorRecommendationService;
use Nizam\Behavior\Domain\ValueObject\BehaviorRecommendation;
use Nizam\Kernel\Application\Query;
use Nizam\Kernel\Application\QueryHandler;
use Nizam\Kernel\Domain\TenantId;
use Nizam\Platform\Support\Assert;

/**
 * Answers {@see ExplainBehaviorProfile} with the explainable recommendations for a profile.
 *
 * The handler loads the tenant-scoped profile, reads the role's approved observations through the
 * {@see ObservationSource}, runs the {@see BehaviorRecommendationService}, and projects each
 * resulting {@see BehaviorRecommendation} into a {@see RecommendationView} carrying its reason,
 * evidence, confidence, and how-to guidance. It produces no proposal and mutates nothing.
 */
final class ExplainBehaviorProfileHandler implements QueryHandler
{
    /**
     * @param BehaviorProfileRepository     $profiles     The profile persistence port.
     * @param ObservationSource             $observations The approved-practice source port.
     * @param BehaviorRecommendationService $recommender  The explainable recommendation service.
     */
    public function __construct(
        private readonly BehaviorProfileRepository $profiles,
        private readonly ObservationSource $observations,
        private readonly BehaviorRecommendationService $recommender,
    ) {
    }

    /**
     * Handle an {@see ExplainBehaviorProfile} query.
     *
     * @throws BehaviorApplicationException When the profile cannot be found for the tenant.
     *
     * @return list<RecommendationView> One view per recommended trait change; empty when none applies.
     */
    public function handle(Query $query): array
    {
        Assert::that(
            $query instanceof ExplainBehaviorProfile,
            'ExplainBehaviorProfileHandler can only handle ExplainBehaviorProfile queries.',
        );

        $tenantId = TenantId::fromString($query->tenantId);
        $profileId = BehaviorProfileId::fromString($query->profileId);

        $profile = $this->profiles->ofId($tenantId, $profileId);
        if ($profile === null) {
            throw BehaviorApplicationException::profileNotFound($profileId->toString());
        }

        $since = $query->since !== null ? new \DateTimeImmutable($query->since) : null;
        $approved = $this->observations->approvedObservationsForRole($tenantId, $profile->roleId(), $since);

        $recommendations = $this->recommender->recommend($profile, $approved);

        return array_map(
            static fn (BehaviorRecommendation $recommendation): RecommendationView
                => RecommendationView::fromDomain($recommendation),
            $recommendations,
        );
    }
}
