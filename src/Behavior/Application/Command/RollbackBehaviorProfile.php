<?php

declare(strict_types=1);

namespace Nizam\Behavior\Application\Command;

use Nizam\Kernel\Application\Command;

/**
 * Intent to roll a behavior profile back to the traits of an earlier version.
 *
 * Rollback is append-only and reversible: rather than deleting history, it restores the target
 * version's traits as a brand-new, higher version whose change-log records the rollback source, so a
 * later rollback can undo it. This is a direct, approval-gated operator action on the profile.
 */
final class RollbackBehaviorProfile implements Command
{
    /**
     * @param string $tenantId   The owning tenant's identifier.
     * @param string $profileId  The profile to roll back.
     * @param int    $toVersion  The earlier version whose traits should be restored.
     * @param string $approvedBy Identity approving the rollback.
     */
    public function __construct(
        public readonly string $tenantId,
        public readonly string $profileId,
        public readonly int $toVersion,
        public readonly string $approvedBy,
    ) {
    }
}
