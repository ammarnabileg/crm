<?php

declare(strict_types=1);

namespace Nizam\Behavior\Application\Command;

use Nizam\Kernel\Application\Command;

/**
 * Intent to raise a behavior change proposal for a role from its approved practice.
 *
 * The engine recommends; it never mutates behavior. This command asks the application layer to read
 * the role's approved observations, consolidate the trait set they support, and — when that differs
 * from the profile's current traits — raise a single pending {@see \Nizam\Behavior\Domain\BehaviorChangeProposal}
 * carrying the recommended traits and their justification. Nothing is applied; a human must approve.
 */
final class ProposeBehaviorChange implements Command
{
    /**
     * @param string $tenantId   The owning tenant's identifier.
     * @param string $roleId     The role whose approved practice should drive the proposal.
     * @param string $proposedBy Identity raising the proposal.
     */
    public function __construct(
        public readonly string $tenantId,
        public readonly string $roleId,
        public readonly string $proposedBy,
    ) {
    }
}
