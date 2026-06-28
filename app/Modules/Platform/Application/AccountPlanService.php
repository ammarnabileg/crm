<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Platform\Application;

use HaHireAI\Core\Contracts\WorkspaceAllowance;
use HaHireAI\Core\Database\Connection;
use HaHireAI\Shared\Ulid;

/**
 * Account-level governance (System Owner): each account (owner user) has a plan
 * that caps how many workspaces it may run *active* at once, plus a renewal date
 * and granted bonus months. Distinct from per-workspace billing — this governs
 * the account's footprint across the platform. The enforcement surface other
 * modules use is the WorkspaceAllowance contract (ARCHITECTURE.md §4).
 */
final class AccountPlanService implements WorkspaceAllowance
{
    /** Workspaces allowed when an account has no plan assigned (free tier). */
    private const FREE_WORKSPACES = 1;

    public function __construct(private readonly Connection $connection)
    {
    }

    /** @return array<string, mixed>|null */
    public function forUser(string $userId): ?array
    {
        return $this->connection->selectOne('SELECT * FROM account_plans WHERE user_id = ?', [$userId]);
    }

    /** Assign (or change) the account's plan. Expiry/bonus are left untouched. */
    public function assignPlan(string $userId, ?string $planId): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $existing = $this->forUser($userId);

        if ($existing === null) {
            $this->connection->statement(
                'INSERT INTO account_plans (id, user_id, plan_id, status, started_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
                [Ulid::generate(), $userId, $planId, 'active', $now, $now, $now],
            );

            return;
        }

        $this->connection->statement(
            'UPDATE account_plans SET plan_id = ?, status = ?, updated_at = ? WHERE user_id = ?',
            [$planId, 'active', $now, $userId],
        );
    }

    /** Grant N free months: extend the renewal date from max(now, current expiry). */
    public function grantMonths(string $userId, int $months): void
    {
        $months = max(1, min(120, $months));
        $this->assignPlan($userId, $this->forUser($userId)['plan_id'] ?? null); // ensure a row exists

        $current = $this->forUser($userId);
        $base = $current['expires_at'] ?? null;
        $baseTs = ($base !== null && strtotime((string) $base . ' UTC') > time()) ? strtotime((string) $base . ' UTC') : time();
        $newExpiry = gmdate('Y-m-d H:i:s', strtotime("+{$months} months", $baseTs));

        $this->connection->statement(
            'UPDATE account_plans SET expires_at = ?, bonus_months = bonus_months + ?, status = ?, updated_at = ? WHERE user_id = ?',
            [$newExpiry, $months, 'active', gmdate('Y-m-d H:i:s'), $userId],
        );
    }

    public function setStatus(string $userId, string $status): void
    {
        $this->assignPlan($userId, $this->forUser($userId)['plan_id'] ?? null);
        $this->connection->statement('UPDATE account_plans SET status = ?, updated_at = ? WHERE user_id = ?', [$status, gmdate('Y-m-d H:i:s'), $userId]);
    }

    /** Max workspaces the account may run, from its plan (or the free tier). */
    public function maxWorkspaces(string $userId): int
    {
        $plan = $this->forUser($userId);
        if ($plan === null || empty($plan['plan_id'])) {
            return self::FREE_WORKSPACES;
        }

        $row = $this->connection->selectOne('SELECT limits FROM plans WHERE id = ?', [(string) $plan['plan_id']]);
        if ($row === null) {
            return self::FREE_WORKSPACES;
        }
        $limits = is_array($row['limits']) ? $row['limits'] : (json_decode((string) $row['limits'], true) ?: []);
        $max = (int) ($limits['workspaces'] ?? self::FREE_WORKSPACES);

        return max(1, $max);
    }

    /** Active (running) workspaces owned by this account. */
    public function activeWorkspaceCount(string $userId): int
    {
        $row = $this->connection->selectOne(
            "SELECT COUNT(*) AS c FROM workspaces WHERE owner_user_id = ? AND status = 'active' AND deleted_at IS NULL",
            [$userId],
        );

        return (int) ($row['c'] ?? 0);
    }

    /** True if the account has a renewal date that has passed (non-renewal). */
    public function isExpired(string $userId): bool
    {
        $plan = $this->forUser($userId);
        $exp = $plan['expires_at'] ?? null;

        return $exp !== null && strtotime((string) $exp . ' UTC') < time();
    }

    /** True if the account is in good standing (not suspended, not expired). */
    public function isUsable(string $userId): bool
    {
        $plan = $this->forUser($userId);
        if ($plan !== null && (string) $plan['status'] !== 'active') {
            return false;
        }

        return ! $this->isExpired($userId);
    }

    /**
     * May this account create another workspace right now?
     *
     * @return array{allowed: bool, reason: string}
     */
    public function canCreateWorkspace(string $userId): array
    {
        $user = $this->connection->selectOne('SELECT can_create_workspaces FROM users WHERE id = ?', [$userId]);
        if ($user !== null && (int) ($user['can_create_workspaces'] ?? 1) === 0) {
            return ['allowed' => false, 'reason' => 'Your account is not permitted to create workspaces. Please contact support.'];
        }
        if (! $this->isUsable($userId)) {
            return ['allowed' => false, 'reason' => 'Your plan is suspended or expired. Please renew or contact support.'];
        }
        $max = $this->maxWorkspaces($userId);
        if ($this->activeWorkspaceCount($userId) >= $max) {
            return ['allowed' => false, 'reason' => "Your plan allows {$max} active workspace(s). Deactivate one or upgrade to add more."];
        }

        return ['allowed' => true, 'reason' => ''];
    }
}
