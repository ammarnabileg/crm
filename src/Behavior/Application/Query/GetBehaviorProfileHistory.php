<?php

declare(strict_types=1);

namespace Nizam\Behavior\Application\Query;

use Nizam\Kernel\Application\Query;

/**
 * Request for the full, append-only revision history of a behavior profile.
 *
 * Answered with a {@see \Nizam\Behavior\Application\Dto\BehaviorProfileView} that includes every
 * {@see \Nizam\Behavior\Application\Dto\BehaviorRevisionView} in version order — the transparent,
 * auditable record of how the role's behavior evolved. Tenant-scoped.
 */
final class GetBehaviorProfileHistory implements Query
{
    /**
     * @param string $tenantId  The owning tenant's identifier.
     * @param string $profileId The profile whose history to read.
     */
    public function __construct(
        public readonly string $tenantId,
        public readonly string $profileId,
    ) {
    }
}
