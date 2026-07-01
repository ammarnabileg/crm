<?php

declare(strict_types=1);

namespace Nizam\Runtime\Orchestration\Testing;

use Nizam\Kernel\Domain\TenantId;
use Nizam\Runtime\Orchestration\Port\AutomationSelector;

/**
 * A simple, deterministic in-memory {@see AutomationSelector} for tests and safe defaults.
 *
 * It selects an automation reference for a worker-declared goal from an in-process map keyed by
 * (tenant, goal); an unmapped goal resolves to null. Selection is deterministic — the same goal always
 * yields the same reference — and tenant-scoped, so different tenants can map the same goal to different
 * automations. This is the safe default the Runtime ships until the automation registry adapter exists.
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
