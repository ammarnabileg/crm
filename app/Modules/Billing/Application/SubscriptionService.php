<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Billing\Application;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Shared\Ulid;
use InvalidArgumentException;

/**
 * Repository for the single per-workspace subscription row (state machine in
 * docs/BILLING_PLATFORM.md §3, STATE_DIAGRAMS §subscription).
 */
final class SubscriptionService
{
    /** Columns the lifecycle/billing services are allowed to update. */
    private const UPDATABLE = [
        'plan_id', 'status', 'trial_ends_at', 'current_period_start', 'current_period_end',
        'grace_ends_at', 'canceled_at', 'cancel_at_period_end', 'provider', 'provider_ref',
    ];

    public function __construct(private readonly Connection $connection)
    {
    }

    /** @return array<string,mixed>|null */
    public function find(string $workspaceId): ?array
    {
        return $this->connection->selectOne('SELECT * FROM subscriptions WHERE workspace_id = ?', [$workspaceId]);
    }

    /** @return array<string,mixed>|null subscription joined with its plan (plan_* + decoded plan) */
    public function findWithPlan(string $workspaceId): ?array
    {
        $sub = $this->find($workspaceId);
        if ($sub === null) {
            return null;
        }

        $plan = $this->connection->selectOne('SELECT * FROM plans WHERE id = ?', [(string) $sub['plan_id']]);
        if ($plan !== null) {
            $plan['features'] = is_string($plan['features'] ?? null) ? (array) json_decode((string) $plan['features'], true) : [];
            $plan['limits'] = is_string($plan['limits'] ?? null) ? (array) json_decode((string) $plan['limits'], true) : [];
        }
        $sub['plan'] = $plan;

        return $sub;
    }

    /**
     * Insert or replace the workspace's subscription row (unique per workspace).
     *
     * @param  array<string, mixed>  $fields  any UPDATABLE column
     */
    public function place(string $workspaceId, string $planId, string $status, array $fields = []): string
    {
        $existing = $this->find($workspaceId);
        $now = gmdate('Y-m-d H:i:s');

        if ($existing === null) {
            $id = Ulid::generate();
            $this->connection->statement(
                'INSERT INTO subscriptions (id, workspace_id, plan_id, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)',
                [$id, $workspaceId, $planId, $status, $now, $now],
            );
        } else {
            $id = (string) $existing['id'];
        }

        $this->update($id, ['plan_id' => $planId, 'status' => $status] + $fields);

        return $id;
    }

    /**
     * Update whitelisted columns on a subscription.
     *
     * @param  array<string, mixed>  $fields
     */
    public function update(string $subscriptionId, array $fields): void
    {
        $sets = [];
        $args = [];

        foreach ($fields as $column => $value) {
            if (! in_array($column, self::UPDATABLE, true)) {
                throw new InvalidArgumentException("Column [{$column}] is not updatable.");
            }
            $sets[] = "`{$column}` = ?";
            $args[] = is_bool($value) ? ($value ? 1 : 0) : $value;
        }

        if ($sets === []) {
            return;
        }

        $sets[] = 'updated_at = ?';
        $args[] = gmdate('Y-m-d H:i:s');
        $args[] = $subscriptionId;

        $this->connection->statement(
            'UPDATE subscriptions SET ' . implode(', ', $sets) . ' WHERE id = ?',
            $args,
        );
    }

    /**
     * Subscriptions whose lifecycle timers may have elapsed at $nowSql.
     *
     * @return list<array<string,mixed>>
     */
    public function dueForLifecycle(string $nowSql): array
    {
        return $this->connection->select(
            "SELECT * FROM subscriptions
              WHERE (status = 'trialing'  AND trial_ends_at IS NOT NULL AND trial_ends_at <= ?)
                 OR (status = 'active'    AND current_period_end IS NOT NULL AND current_period_end <= ?)
                 OR (status = 'past_due'  AND grace_ends_at IS NOT NULL AND grace_ends_at <= ?)",
            [$nowSql, $nowSql, $nowSql],
        );
    }
}
