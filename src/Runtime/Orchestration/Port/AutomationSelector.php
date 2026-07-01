<?php

declare(strict_types=1);

namespace Nizam\Runtime\Orchestration\Port;

use Nizam\Kernel\Domain\TenantId;

/**
 * The port through which the Runtime selects an automation for a worker's stated goal.
 *
 * A worker never talks to an automation engine directly. When it needs an automation it declares a
 * *goal* and the Runtime selects the concrete automation reference to satisfy it — keeping automation
 * selection at the single Runtime seam and auditable. Selection is tenant-scoped because a tenant only
 * has certain automations installed. The orchestration layer depends only on this interface; a simple,
 * deterministic in-memory adapter serves tests and safe defaults, and the production adapter is backed
 * by the automation registry.
 */
interface AutomationSelector
{
    /**
     * Select an automation reference satisfying a goal for a tenant, or null when none matches.
     *
     * @param TenantId $tenantId The owning tenant.
     * @param string   $goal     The automation goal the worker declared.
     *
     * @return string|null The selected automation reference, or null when none matches the goal.
     */
    public function select(TenantId $tenantId, string $goal): ?string;
}
