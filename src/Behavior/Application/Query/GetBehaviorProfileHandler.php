<?php

declare(strict_types=1);

namespace Nizam\Behavior\Application\Query;

use Nizam\Behavior\Application\Dto\BehaviorProfileView;
use Nizam\Behavior\Application\Exception\BehaviorApplicationException;
use Nizam\Behavior\Domain\BehaviorProfileId;
use Nizam\Behavior\Domain\Port\BehaviorProfileRepository;
use Nizam\Kernel\Application\Query;
use Nizam\Kernel\Application\QueryHandler;
use Nizam\Kernel\Domain\TenantId;
use Nizam\Platform\Support\Assert;

/**
 * Answers {@see GetBehaviorProfile} with a read-only view of the profile's current state.
 *
 * The handler loads the tenant-scoped profile and projects it into a
 * {@see BehaviorProfileView} without history. It mutates nothing.
 */
final class GetBehaviorProfileHandler implements QueryHandler
{
    /**
     * @param BehaviorProfileRepository $profiles The profile persistence port.
     */
    public function __construct(
        private readonly BehaviorProfileRepository $profiles,
    ) {
    }

    /**
     * Handle a {@see GetBehaviorProfile} query.
     *
     * @throws BehaviorApplicationException When the profile cannot be found for the tenant.
     *
     * @return BehaviorProfileView The current-state view of the profile.
     */
    public function handle(Query $query): BehaviorProfileView
    {
        Assert::that(
            $query instanceof GetBehaviorProfile,
            'GetBehaviorProfileHandler can only handle GetBehaviorProfile queries.',
        );

        $tenantId = TenantId::fromString($query->tenantId);
        $profileId = BehaviorProfileId::fromString($query->profileId);

        $profile = $this->profiles->ofId($tenantId, $profileId);
        if ($profile === null) {
            throw BehaviorApplicationException::profileNotFound($profileId->toString());
        }

        return BehaviorProfileView::fromDomain($profile);
    }
}
