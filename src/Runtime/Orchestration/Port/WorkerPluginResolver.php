<?php

declare(strict_types=1);

namespace Nizam\Runtime\Orchestration\Port;

use Nizam\Kernel\Domain\TenantId;
use Nizam\Platform\Plugin\Contract\WorkerPlugin;

/**
 * The port through which the {@see \Nizam\Runtime\Orchestration\WorkerCoordinator} loads the Worker
 * plugin a task is dispatched to.
 *
 * Given a task's worker reference, this returns the concrete {@see WorkerPlugin} — a plugin-kind
 * contract from the Plugin Platform — that performs the unit of work. Resolution is tenant-scoped
 * because a tenant only sees the plugins installed for it. Workers are only ever obtained and invoked
 * through the Runtime; they never resolve each other. The orchestration layer depends only on this
 * interface and the plugin contract; the production adapter is backed by the Plugin Platform's
 * registry/manager, and an in-memory, seedable adapter serves tests and safe defaults.
 */
interface WorkerPluginResolver
{
    /**
     * Resolve the worker plugin with the given reference for a tenant, or null when unavailable.
     *
     * @param TenantId $tenantId  The owning tenant.
     * @param string   $workerRef The worker plugin name to load.
     *
     * @return WorkerPlugin|null The resolved worker plugin, or null when unavailable.
     */
    public function resolve(TenantId $tenantId, string $workerRef): ?WorkerPlugin;
}
