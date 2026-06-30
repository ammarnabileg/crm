<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Billing\Application;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Shared\Ulid;
use InvalidArgumentException;

/**
 * Repository for the composed monthly plan per workspace and its features/add-ons
 * (docs/WALLET_AND_BILLING.md §4–§7). Pure persistence — pricing, charging and
 * lifecycle live in PlanComposer / SubscriptionLifecycle.
 */
final class WorkspacePlanService
{
    private const UPDATABLE = [
        'status', 'seats_paid', 'monthly_cost_cents', 'period_start', 'period_end',
        'auto_renew', 'locked_at',
    ];

    public function __construct(private readonly Connection $connection)
    {
    }

    /** @return array<string,mixed>|null */
    public function find(string $workspaceId): ?array
    {
        return $this->connection->selectOne('SELECT * FROM workspace_plans WHERE workspace_id = ?', [$workspaceId]);
    }

    /** True if the workspace has composed a plan (so wallet billing applies). */
    public function hasPlan(string $workspaceId): bool
    {
        return $this->find($workspaceId) !== null;
    }

    public function isLocked(string $workspaceId): bool
    {
        $plan = $this->find($workspaceId);

        return $plan !== null && (string) $plan['status'] === 'locked';
    }

    /** Insert or fetch the single per-workspace plan row; returns its id. */
    public function ensure(string $workspaceId): string
    {
        $existing = $this->find($workspaceId);
        if ($existing !== null) {
            return (string) $existing['id'];
        }

        $id = Ulid::generate();
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->statement(
            'INSERT INTO workspace_plans (id, workspace_id, status, seats_paid, monthly_cost_cents, auto_renew, created_at, updated_at)
             VALUES (?, ?, ?, 0, 0, 1, ?, ?)',
            [$id, $workspaceId, 'active', $now, $now],
        );

        return $id;
    }

    /** @param array<string,mixed> $fields */
    public function update(string $planId, array $fields): void
    {
        $sets = [];
        $args = [];
        foreach ($fields as $col => $val) {
            if (! in_array($col, self::UPDATABLE, true)) {
                throw new InvalidArgumentException("Column [{$col}] is not updatable.");
            }
            $sets[] = "`{$col}` = ?";
            $args[] = is_bool($val) ? ($val ? 1 : 0) : $val;
        }
        if ($sets === []) {
            return;
        }
        $sets[] = 'updated_at = ?';
        $args[] = gmdate('Y-m-d H:i:s');
        $args[] = $planId;

        $this->connection->statement('UPDATE workspace_plans SET ' . implode(', ', $sets) . ' WHERE id = ?', $args);
    }

    /** Replace the base feature set with a price snapshot. */
    public function setFeatures(string $workspaceId, string $planId, array $featurePrices): void
    {
        $this->connection->statement('DELETE FROM workspace_plan_features WHERE plan_id = ?', [$planId]);
        $now = gmdate('Y-m-d H:i:s');
        foreach ($featurePrices as $key => $cents) {
            $this->connection->statement(
                'INSERT INTO workspace_plan_features (id, workspace_id, plan_id, feature_key, price_cents, created_at) VALUES (?, ?, ?, ?, ?, ?)',
                [Ulid::generate(), $workspaceId, $planId, (string) $key, (int) $cents, $now],
            );
        }
    }

    /** @return list<string> base feature keys in the plan */
    public function baseFeatureKeys(string $planId): array
    {
        $rows = $this->connection->select('SELECT feature_key FROM workspace_plan_features WHERE plan_id = ?', [$planId]);

        return array_map(static fn (array $r): string => (string) $r['feature_key'], $rows);
    }

    /** Record a mid-term add-on (feature or seat) that expires with the plan. */
    public function addAddon(string $workspaceId, string $planId, string $kind, ?string $ref, int $priceCents, ?string $expiresAt, ?string $actorUserId): string
    {
        $id = Ulid::generate();
        $this->connection->statement(
            'INSERT INTO workspace_plan_addons (id, workspace_id, plan_id, kind, ref, price_cents, expires_at, actor_user_id, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$id, $workspaceId, $planId, $kind, $ref, $priceCents, $expiresAt, $actorUserId, gmdate('Y-m-d H:i:s')],
        );

        return $id;
    }

    /**
     * Active add-ons for the plan at $nowSql (not yet expired).
     *
     * @return list<array<string,mixed>>
     */
    public function activeAddons(string $planId, ?string $nowSql = null): array
    {
        $nowSql ??= gmdate('Y-m-d H:i:s');

        return $this->connection->select(
            'SELECT * FROM workspace_plan_addons WHERE plan_id = ? AND (expires_at IS NULL OR expires_at > ?) ORDER BY created_at DESC',
            [$planId, $nowSql],
        );
    }

    /**
     * Every feature key the workspace currently has from its composed plan: base
     * features + active feature add-ons. Empty list when no plan / no features.
     *
     * @return list<string>
     */
    public function activeFeatureKeys(string $workspaceId, ?string $nowSql = null): array
    {
        $plan = $this->find($workspaceId);
        if ($plan === null) {
            return [];
        }
        $planId = (string) $plan['id'];

        $keys = $this->baseFeatureKeys($planId);
        foreach ($this->activeAddons($planId, $nowSql) as $addon) {
            if ((string) $addon['kind'] === 'feature' && $addon['ref'] !== null) {
                $keys[] = (string) $addon['ref'];
            }
        }

        return array_values(array_unique($keys));
    }

    /** Seats covered right now = base seats_paid + active seat add-ons. */
    public function coveredSeats(string $workspaceId, ?string $nowSql = null): int
    {
        $plan = $this->find($workspaceId);
        if ($plan === null) {
            return 0;
        }
        $seats = (int) $plan['seats_paid'];
        foreach ($this->activeAddons((string) $plan['id'], $nowSql) as $addon) {
            if ((string) $addon['kind'] === 'seat') {
                $seats++;
            }
        }

        return $seats;
    }
}
