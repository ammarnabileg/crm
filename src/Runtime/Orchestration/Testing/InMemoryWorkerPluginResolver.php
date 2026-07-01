<?php

declare(strict_types=1);

namespace Nizam\Runtime\Orchestration\Testing;

use Nizam\Kernel\Domain\TenantId;
use Nizam\Platform\Plugin\Contract\WorkerPlugin;
use Nizam\Runtime\Orchestration\Port\WorkerPluginResolver;

/**
 * A real, seedable in-memory {@see WorkerPluginResolver} for tests and safe defaults.
 *
 * It resolves worker plugins from an in-process map keyed by (tenant, worker reference); an unseeded
 * reference resolves to null, letting the coordinator reject it exactly as a missing production plugin
 * would. Resolution is tenant-scoped, mirroring that a tenant only sees the plugins installed for it.
 * This is a working adapter holding real {@see WorkerPlugin} instances, not a stub.
 */
final class InMemoryWorkerPluginResolver implements WorkerPluginResolver
{
    /** @var array<string, WorkerPlugin> */
    private array $workers = [];

    /**
     * Seed a resolvable worker plugin for a tenant under a reference (defaulting to its manifest name).
     */
    public function seed(TenantId $tenantId, WorkerPlugin $worker, ?string $workerRef = null): self
    {
        $ref = $workerRef ?? $worker->manifest()->name();
        $this->workers[$this->key($tenantId, $ref)] = $worker;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function resolve(TenantId $tenantId, string $workerRef): ?WorkerPlugin
    {
        return $this->workers[$this->key($tenantId, $workerRef)] ?? null;
    }

    /**
     * The composite map key scoping a worker to its tenant and reference.
     */
    private function key(TenantId $tenantId, string $workerRef): string
    {
        return $tenantId->toString() . "\0" . $workerRef;
    }
}
