<?php

declare(strict_types=1);

namespace Nizam\Behavior\Application\Command;

use Nizam\Behavior\Application\Exception\BehaviorApplicationException;
use Nizam\Behavior\Domain\BehaviorProfile;
use Nizam\Behavior\Domain\Port\BehaviorEventPublisher;
use Nizam\Behavior\Domain\Port\BehaviorProfileRepository;
use Nizam\Behavior\Domain\RoleId;
use Nizam\Kernel\Application\Command;
use Nizam\Kernel\Application\CommandHandler;
use Nizam\Kernel\Domain\Clock;
use Nizam\Kernel\Domain\TenantId;
use Nizam\Platform\Support\Assert;

/**
 * Drafts a new behavior profile for a role and persists it.
 *
 * The handler enforces the one-profile-per-role rule, mints a fresh identity from the repository,
 * asks the {@see BehaviorProfile} aggregate to draft itself (which records a drafted event), saves
 * the aggregate, then pulls its recorded domain events and hands them to the
 * {@see BehaviorEventPublisher}. It contains no business rules of its own beyond orchestration.
 */
final class DraftBehaviorProfileHandler implements CommandHandler
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
     * Handle a {@see DraftBehaviorProfile} command.
     *
     * @throws BehaviorApplicationException When a profile already exists for the role.
     *
     * @return string The identity of the newly drafted profile.
     */
    public function handle(Command $command): string
    {
        Assert::that(
            $command instanceof DraftBehaviorProfile,
            'DraftBehaviorProfileHandler can only handle DraftBehaviorProfile commands.',
        );

        $tenantId = TenantId::fromString($command->tenantId);
        $roleId = RoleId::fromString($command->roleId);

        if ($this->profiles->existsForRole($tenantId, $roleId)) {
            throw BehaviorApplicationException::profileAlreadyExistsForRole($roleId->toString());
        }

        $profile = BehaviorProfile::draft(
            $this->profiles->nextIdentity(),
            $tenantId,
            $roleId,
            $command->initialTraits,
            $command->draftedBy,
            $this->clock,
        );

        $this->profiles->save($profile);
        $this->publisher->publish($profile->pullDomainEvents());

        return $profile->profileId()->toString();
    }
}
