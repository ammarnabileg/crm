<?php

declare(strict_types=1);

namespace Nizam\Behavior\Application\Command;

use Nizam\Behavior\Domain\ValueObject\BehaviorTraits;
use Nizam\Kernel\Application\Command;

/**
 * Intent to draft a brand-new behavior profile for a role at version 1.
 *
 * The command carries the owning tenant, the role to bind the profile to, the initial trait set the
 * profile should start from, and the identity drafting it. The resulting profile begins in draft and
 * does not govern behavior until it is separately activated. Elevated risk in the initial traits is
 * already gated by {@see BehaviorTraits}'s construction guard, so this DTO simply transports the
 * validated traits.
 */
final class DraftBehaviorProfile implements Command
{
    /**
     * @param string         $tenantId      The owning tenant's identifier.
     * @param string         $roleId        The role to bind the profile to (immutable thereafter).
     * @param BehaviorTraits $initialTraits The traits the profile should start from.
     * @param string         $draftedBy     Identity drafting the profile.
     */
    public function __construct(
        public readonly string $tenantId,
        public readonly string $roleId,
        public readonly BehaviorTraits $initialTraits,
        public readonly string $draftedBy,
    ) {
    }
}
