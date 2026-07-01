<?php

declare(strict_types=1);

namespace Nizam\Runtime\Orchestration\Port;

use Nizam\Kernel\Domain\TenantId;
use Nizam\Kernel\Domain\UserId;
use Nizam\Runtime\Orchestration\ValueObject\ResolvedUser;

/**
 * The port through which the {@see \Nizam\Runtime\Orchestration\MasterOrchestrator} resolves the acting
 * user a request runs on behalf of.
 *
 * When a request names a user, the orchestrator resolves that user *within the request's tenant* so a
 * request on behalf of an unknown, cross-tenant, or inactive user is rejected before any execution
 * begins. The orchestration layer depends only on this interface; the production adapter reads the user
 * directory, and an in-memory, seedable adapter serves tests and safe defaults.
 */
interface UserProvider
{
    /**
     * Resolve the user with the given identity within a tenant, or null when none exists there.
     *
     * @param TenantId $tenantId The tenant the user must belong to.
     * @param UserId   $userId   The user to resolve.
     *
     * @return ResolvedUser|null The resolved user, or null when unknown for that tenant.
     */
    public function resolve(TenantId $tenantId, UserId $userId): ?ResolvedUser;
}
