<?php

declare(strict_types=1);

namespace Nizam\Runtime\Infrastructure\Provider\InMemory;

use Nizam\Kernel\Domain\TenantId;
use Nizam\Runtime\Orchestration\Port\AutomationSelector;

/**
 * The Infrastructure-layer, simple, deterministic in-memory {@see AutomationSelector} — the safe default
 * the {@see \Nizam\Runtime\Infrastructure\RuntimeServiceProvider} binds until the automation registry
 * adapter exists.
 *
 * It selects an automation reference for a worker-declared goal from an in-process map keyed by
 * (tenant, goal); an unmapped goal resolves to null. Selection is deterministic — the same goal always
 * yields the same reference — and tenant-scoped, so different tenants can map the same goal to different
 * automations. A worker never talks to an automation engine directly; it declares a goal and the Runtime
 * selects, keeping selection auditable at the single Runtime seam.
 */
final class InMemoryAutomationSelector implements AutomationSelector
{
    /** @var array<string, string> */
    private array $automations = [];

    /**
     * Map a goal to an automation reference for a tenant.
     */
    public function register(TenantId $tenantId, string $goal, string $automationRef): self
    {
        $this->automations[$this->key($tenantId, $goal)] = $automationRef;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function select(TenantId $tenantId, string $goal): ?string
    {
        return $this->automations[$this->key($tenantId, $goal)] ?? null;
    }

    /**
     * The composite map key scoping an automation to its tenant and goal.
     */
    private function key(TenantId $tenantId, string $goal): string
    {
        return $tenantId->toString() . "\0" . $goal;
    }
}
