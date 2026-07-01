<?php

declare(strict_types=1);

namespace HaHireAI\Core\Billing;

use HaHireAI\Core\Contracts\WorkspaceSeatGuard;

/**
 * The default seat guard: permissive — never blocks adding a member. Active when
 * the Billing module is disabled or a workspace has no composed plan, preserving
 * the pre-billing behaviour (Constitution mindset #12 — backward compatibility).
 */
final class NullWorkspaceSeatGuard implements WorkspaceSeatGuard
{
    public function canAddBillableMember(string $workspaceId): bool
    {
        return true;
    }

    public function denyReason(string $workspaceId): string
    {
        return '';
    }
}
