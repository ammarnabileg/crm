<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Observability\Application;

use HaHireAI\Core\Database\Connection;
use Throwable;

/**
 * Computes the platform-wide operational snapshot from existing domain tables —
 * no separate metrics pipeline to drift out of sync (docs/OBSERVABILITY.md §3).
 * Every read is resilient: a missing/locked table contributes 0, never an error.
 */
final class MetricsService
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /** @return array<string, mixed> */
    public function platformSnapshot(): array
    {
        return [
            'tenants' => [
                'workspaces' => $this->int('SELECT COUNT(*) AS c FROM workspaces WHERE deleted_at IS NULL'),
                'active' => $this->int("SELECT COUNT(*) AS c FROM workspaces WHERE status = 'active' AND deleted_at IS NULL"),
            ],
            'users' => [
                'total' => $this->int('SELECT COUNT(*) AS c FROM users WHERE deleted_at IS NULL'),
                'system_owners' => $this->int('SELECT COUNT(*) AS c FROM users WHERE is_system_owner = 1'),
            ],
            'subscriptions' => [
                'trialing' => $this->int("SELECT COUNT(*) AS c FROM subscriptions WHERE status = 'trialing'"),
                'active' => $this->int("SELECT COUNT(*) AS c FROM subscriptions WHERE status = 'active'"),
                'past_due' => $this->int("SELECT COUNT(*) AS c FROM subscriptions WHERE status = 'past_due'"),
                'suspended' => $this->int("SELECT COUNT(*) AS c FROM subscriptions WHERE status = 'suspended'"),
                'canceled' => $this->int("SELECT COUNT(*) AS c FROM subscriptions WHERE status = 'canceled'"),
                'mrr_cents' => $this->mrrCents(),
            ],
            'recruitment' => [
                'jobs' => $this->int('SELECT COUNT(*) AS c FROM jobs WHERE deleted_at IS NULL'),
                'published_jobs' => $this->int("SELECT COUNT(*) AS c FROM jobs WHERE status = 'published' AND deleted_at IS NULL"),
                'applications' => $this->int('SELECT COUNT(*) AS c FROM applications WHERE deleted_at IS NULL'),
            ],
            'ai' => [
                'sessions' => $this->int('SELECT COUNT(*) AS c FROM ai_sessions'),
                'tokens' => $this->int('SELECT COALESCE(SUM(input_tokens + output_tokens), 0) AS c FROM ai_sessions'),
                'cost_cents' => $this->int('SELECT COALESCE(SUM(cost_cents), 0) AS c FROM ai_sessions'),
            ],
            'automation' => [
                'executions' => $this->int('SELECT COUNT(*) AS c FROM workflow_executions'),
                'failed' => $this->int("SELECT COUNT(*) AS c FROM workflow_executions WHERE status = 'failed'"),
            ],
            'integration' => [
                'api_tokens' => $this->int('SELECT COUNT(*) AS c FROM api_tokens WHERE revoked_at IS NULL'),
                'webhook_deliveries' => $this->int('SELECT COUNT(*) AS c FROM webhook_deliveries'),
                'webhook_failures' => $this->int("SELECT COUNT(*) AS c FROM webhook_deliveries WHERE status = 'failed'"),
            ],
            'health' => [
                'errors_24h' => $this->int('SELECT COUNT(*) AS c FROM error_events WHERE occurred_at >= ?', [gmdate('Y-m-d H:i:s', time() - 86400)]),
                'open_alerts' => $this->int("SELECT COUNT(*) AS c FROM alerts WHERE status = 'open'"),
            ],
        ];
    }

    /** Approximate monthly recurring revenue from billable subscriptions. */
    private function mrrCents(): int
    {
        try {
            $rows = $this->connection->select(
                "SELECT p.price_cents AS price, p.`interval` AS itv
                   FROM subscriptions s JOIN plans p ON p.id = s.plan_id
                  WHERE s.status IN ('active', 'trialing', 'past_due')",
            );
        } catch (Throwable) {
            return 0;
        }

        $mrr = 0;
        foreach ($rows as $row) {
            $price = (int) $row['price'];
            $mrr += (string) $row['itv'] === 'year' ? (int) round($price / 12) : $price;
        }

        return $mrr;
    }

    /** @param list<mixed> $args */
    private function int(string $sql, array $args = []): int
    {
        try {
            $row = $this->connection->selectOne($sql, $args);

            return (int) ($row['c'] ?? 0);
        } catch (Throwable) {
            return 0;
        }
    }
}
