<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Billing\Application;

use HaHireAI\Core\Contracts\WorkspaceSeatGuard;

/**
 * Enforces seat limits for the Memberships module without exposing Billing
 * internals (docs/WALLET_AND_BILLING.md §5). A workspace with no composed plan is
 * permissive (pre-billing/legacy); otherwise a new active billable member is only
 * allowed when the funded seats still have room. A locked plan blocks everything.
 */
final class WorkspaceSeatGuardAdapter implements WorkspaceSeatGuard
{
    public function __construct(
        private readonly SeatCounter $seats,
        private readonly WorkspacePlanService $plans,
    ) {
    }

    public function canAddBillableMember(string $workspaceId): bool
    {
        $plan = $this->plans->find($workspaceId);
        if ($plan === null) {
            return true; // no composed plan → un-gated (free/legacy)
        }
        if ((string) $plan['status'] === 'locked') {
            return false;
        }

        // Room exists when current billable staff is below the funded seat count.
        return $this->seats->billableSeats($workspaceId) < $this->plans->coveredSeats($workspaceId);
    }

    public function denyReason(string $workspaceId): string
    {
        $plan = $this->plans->find($workspaceId);
        if ($plan !== null && (string) $plan['status'] === 'locked') {
            return 'Your plan is paused. Top up the wallet and re-activate it in Billing first.';
        }

        return 'All paid seats are in use. Add a seat in Billing (top up if needed) before adding another member.';
    }
}
