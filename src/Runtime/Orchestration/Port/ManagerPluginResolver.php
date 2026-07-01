<?php

declare(strict_types=1);

namespace Nizam\Runtime\Orchestration\Port;

use Nizam\Kernel\Domain\TenantId;
use Nizam\Platform\Plugin\Contract\ManagerPlugin;

/**
 * The port through which the {@see \Nizam\Runtime\Orchestration\MasterOrchestrator} loads the Manager
 * plugin that leads a resolved department.
 *
 * Given the manager reference the {@see DepartmentResolver} produced, this returns the concrete
 * {@see ManagerPlugin} — a plugin-kind contract from the Plugin Platform — that plans the work and
 * later decides on the merged result. Resolution is tenant-scoped because a tenant only sees the
 * plugins installed for it. The orchestration layer depends only on this interface and the plugin
 * contract; the production adapter is backed by the Plugin Platform's registry/manager, and an
 * in-memory, seedable adapter serves tests and safe defaults.
 */
interface ManagerPluginResolver
{
    /**
     * Resolve the manager plugin with the given reference for a tenant, or null when unavailable.
     *
     * @param TenantId $tenantId   The owning tenant.
     * @param string   $managerRef The manager plugin name to load.
     *
     * @return ManagerPlugin|null The resolved manager plugin, or null when unavailable.
     */
    public function resolve(TenantId $tenantId, string $managerRef): ?ManagerPlugin;
}
