<?php

declare(strict_types=1);

namespace HaHireAI\Core\Contracts;

/**
 * Answers whether a workspace may take on one more **billable** staff member
 * (every active staff membership beyond the free Owner consumes a paid seat —
 * docs/WALLET_AND_BILLING.md §5). Lets the Memberships module enforce seat limits
 * without depending on Billing internals (ARCHITECTURE.md §4). Billing binds the
 * real implementation; Core binds a permissive null default so memberships keep
 * working with Billing disabled or when a workspace has no composed plan.
 */
interface WorkspaceSeatGuard
{
    /** True if another active billable member fits within the funded seats. */
    public function canAddBillableMember(string $workspaceId): bool;

    /** A human-friendly reason to show when it cannot (empty when allowed). */
    public function denyReason(string $workspaceId): string;
}
