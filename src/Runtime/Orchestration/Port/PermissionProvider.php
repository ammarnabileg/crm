<?php

declare(strict_types=1);

namespace Nizam\Runtime\Orchestration\Port;

use Nizam\Kernel\Domain\TenantId;
use Nizam\Kernel\Domain\UserId;
use Nizam\Platform\Plugin\PermissionSet;

/**
 * The port through which the {@see \Nizam\Runtime\Orchestration\MasterOrchestrator} resolves the
 * permissions granted for a request.
 *
 * The resolved {@see PermissionSet} is placed on the request-scoped
 * {@see \Nizam\Runtime\Orchestration\ValueObject\ExecutionContext} and is what the
 * {@see \Nizam\Runtime\Orchestration\WorkerCoordinator} checks before dispatching each worker — keeping
 * authorization at the single Runtime seam rather than inside plugins. The set is resolved for the
 * (tenant, user, intent) triple so grants can be scoped per capability. The orchestration layer depends
 * only on this interface; the production adapter reads the tenant's grants, and an in-memory, seedable
 * adapter serves tests and safe defaults.
 */
interface PermissionProvider
{
    /**
     * Resolve the permissions granted for the given tenant, optional user, and intent.
     *
     * @param TenantId    $tenantId  The owning tenant.
     * @param UserId|null $userId    The acting user, if any.
     * @param string      $intentRef The requested capability/intent identifier.
     *
     * @return PermissionSet The permissions granted for the request (possibly empty).
     */
    public function resolve(TenantId $tenantId, ?UserId $userId, string $intentRef): PermissionSet;
}
