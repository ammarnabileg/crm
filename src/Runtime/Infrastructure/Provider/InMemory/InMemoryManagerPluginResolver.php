<?php

declare(strict_types=1);

namespace Nizam\Runtime\Infrastructure\Provider\InMemory;

use Nizam\Kernel\Domain\TenantId;
use Nizam\Platform\Plugin\Contract\ManagerPlugin;
use Nizam\Runtime\Orchestration\Port\ManagerPluginResolver;

/**
 * The Infrastructure-layer, real, seedable in-memory {@see ManagerPluginResolver} — the safe default the
 * {@see \Nizam\Runtime\Infrastructure\RuntimeServiceProvider} binds until the Plugin Platform registry
 * adapter exists.
 *
 * It resolves manager plugins from an in-process map keyed by (tenant, manager reference); an unseeded
 * reference resolves to null, letting the orchestrator reject it exactly as a missing production plugin
 * would. Resolution is tenant-scoped, mirroring that a tenant only sees the plugins installed for it. It
 * holds real {@see ManagerPlugin} instances, so it is a working adapter, not a stub.
 */
final class InMemoryManagerPluginResolver implements ManagerPluginResolver
{
    /** @var array<string, ManagerPlugin> */
    private array $managers = [];

    /**
     * Seed a resolvable manager plugin for a tenant under a reference (defaulting to its manifest name).
     */
    public function seed(TenantId $tenantId, ManagerPlugin $manager, ?string $managerRef = null): self
    {
        $ref = $managerRef ?? $manager->manifest()->name();
        $this->managers[$this->key($tenantId, $ref)] = $manager;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function resolve(TenantId $tenantId, string $managerRef): ?ManagerPlugin
    {
        return $this->managers[$this->key($tenantId, $managerRef)] ?? null;
    }

    /**
     * The composite map key scoping a manager to its tenant and reference.
     */
    private function key(TenantId $tenantId, string $managerRef): string
    {
        return $tenantId->toString() . "\0" . $managerRef;
    }
}
