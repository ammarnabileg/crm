<?php

declare(strict_types=1);

namespace HaHireAI\Core\Contracts;

/**
 * The account-governance surface the Workspaces module depends on to decide
 * whether an account may create/run another workspace — never the concrete
 * Platform\Application\AccountPlanService (ARCHITECTURE.md §4). Bound at boot.
 */
interface WorkspaceAllowance
{
    /**
     * May this account create another workspace right now?
     *
     * @return array{allowed: bool, reason: string}
     */
    public function canCreateWorkspace(string $userId): array;

    /**
     * May this account turn an *existing* workspace back on (within the plan
     * cap)? Unlike creation this ignores the block flag — it governs how many
     * workspaces run at once, not whether new ones may be made.
     *
     * @return array{allowed: bool, reason: string}
     */
    public function canActivateWorkspace(string $userId): array;

    /** Max workspaces the account may run active at once. */
    public function maxWorkspaces(string $userId): int;

    /** Active (running) workspaces owned by this account. */
    public function activeWorkspaceCount(string $userId): int;

    /** True if the account is in good standing (plan active, not expired). */
    public function isUsable(string $userId): bool;
}
