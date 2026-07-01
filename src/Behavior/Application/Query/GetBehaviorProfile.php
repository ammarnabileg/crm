<?php

declare(strict_types=1);

namespace Nizam\Behavior\Application\Query;

use Nizam\Kernel\Application\Query;

/**
 * Request for the current state of a single behavior profile.
 *
 * Answered with a {@see \Nizam\Behavior\Application\Dto\BehaviorProfileView} carrying the profile's
 * identity, role binding, status, current version, and current traits — without its full history.
 * Tenant-scoped: a profile is only ever readable by its owning tenant.
 */
final class GetBehaviorProfile implements Query
{
    /**
     * @param string $tenantId  The owning tenant's identifier.
     * @param string $profileId The profile to read.
     */
    public function __construct(
        public readonly string $tenantId,
        public readonly string $profileId,
    ) {
    }
}
