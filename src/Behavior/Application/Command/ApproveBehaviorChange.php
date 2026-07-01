<?php

declare(strict_types=1);

namespace Nizam\Behavior\Application\Command;

use Nizam\Kernel\Application\Command;

/**
 * Intent to approve a pending behavior change proposal and apply it to its target profile.
 *
 * Approval is the deliberate human act the engine waits for. The command approves the proposal and,
 * in the same unit of work, applies its recommended traits to the bound profile as a new appended
 * version — a rollback proposal restores an earlier version, an ordinary proposal activates the
 * proposed traits. Both the proposal decision and the profile change are audited and announced.
 */
final class ApproveBehaviorChange implements Command
{
    /**
     * @param string $tenantId   The owning tenant's identifier.
     * @param string $proposalId The proposal to approve and apply.
     * @param string $approvedBy Identity approving the change.
     */
    public function __construct(
        public readonly string $tenantId,
        public readonly string $proposalId,
        public readonly string $approvedBy,
    ) {
    }
}
