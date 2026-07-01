<?php

declare(strict_types=1);

namespace Nizam\Behavior\Application\Command;

use Nizam\Behavior\Application\Dto\BehaviorProfileView;
use Nizam\Behavior\Application\Exception\BehaviorApplicationException;
use Nizam\Behavior\Domain\BehaviorProfileId;
use Nizam\Behavior\Domain\Port\BehaviorEventPublisher;
use Nizam\Behavior\Domain\Port\BehaviorProfileRepository;
use Nizam\Kernel\Application\Command;
use Nizam\Kernel\Application\CommandHandler;
use Nizam\Kernel\Domain\Clock;
use Nizam\Kernel\Domain\TenantId;
use Nizam\Platform\Support\Assert;

/**
 * Rolls a behavior profile back to the traits of an earlier version.
 *
 * The handler loads the profile, asks the aggregate to roll back to the requested version (which
 * appends a new, reversible revision and records a rolled-back event), saves it, and publishes the
 * pulled events. All history is preserved.
 */
final class RollbackBehaviorProfileHandler implements CommandHandler
{
    /**
     * @param BehaviorProfileRepository $profiles  The profile persistence port.
     * @param BehaviorEventPublisher    $publisher The port that dispatches pulled domain events.
     * @param Clock                     $clock     The time source handed to the aggregate.
     */
    public function __construct(
        private readonly BehaviorProfileRepository $profiles,
        private readonly BehaviorEventPublisher $publisher,
        private readonly Clock $clock,
    ) {
    }

    /**
     * Handle a {@see RollbackBehaviorProfile} command.
     *
     * @throws BehaviorApplicationException When the profile cannot be found for the tenant.
     *
     * @return BehaviorProfileView The profile view after the rollback.
     */
    public function handle(Command $command): BehaviorProfileView
    {
        Assert::that(
            $command instanceof RollbackBehaviorProfile,
            'RollbackBehaviorProfileHandler can only handle RollbackBehaviorProfile commands.',
        );

        $tenantId = TenantId::fromString($command->tenantId);
        $profileId = BehaviorProfileId::fromString($command->profileId);

        $profile = $this->profiles->ofId($tenantId, $profileId);
        if ($profile === null) {
            throw BehaviorApplicationException::profileNotFound($profileId->toString());
        }

        $profile->rollbackTo($command->toVersion, $command->approvedBy, $this->clock);

        $this->profiles->save($profile);
        $this->publisher->publish($profile->pullDomainEvents());

        return BehaviorProfileView::fromDomain($profile);
    }
}
