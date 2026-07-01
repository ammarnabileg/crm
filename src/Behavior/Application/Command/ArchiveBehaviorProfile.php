<?php

declare(strict_types=1);

namespace Nizam\Behavior\Application\Command;

use Nizam\Kernel\Application\Command;

/**
 * Intent to archive a behavior profile, retiring it from use while retaining its history.
 *
 * Archival is a terminal lifecycle transition: the profile stops governing its role but its full,
 * append-only revision history is preserved for audit. An already-archived profile cannot be
 * archived again.
 */
final class ArchiveBehaviorProfile implements Command
{
    /**
     * @param string $tenantId   The owning tenant's identifier.
     * @param string $profileId  The profile to archive.
     * @param string $archivedBy Identity performing the archival.
     */
    public function __construct(
        public readonly string $tenantId,
        public readonly string $profileId,
        public readonly string $archivedBy,
    ) {
    }
}
