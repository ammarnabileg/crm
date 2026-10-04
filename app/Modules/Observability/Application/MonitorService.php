<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Observability\Application;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Shared\Ulid;

/**
 * Evaluates platform monitors and opens/resolves alerts. Driven by a scheduled
 * tick (`$nowTs` injectable for tests). A monitor opens at most one alert at a
 * time and auto-resolves when its condition clears (docs/OBSERVABILITY.md §5).
 */
final class MonitorService
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @return array{opened: int, resolved: int}
     */
    public function tick(?int $nowTs = null): array
    {
        $nowTs ??= time();
        $opened = 0;
        $resolved = 0;

        foreach ($this->evaluateAll($nowTs) as $monitor) {
            $open = $this->openAlertFor($monitor['key']);

            if ($monitor['tripped'] && $open === null) {
                $this->open($monitor, $nowTs);
                $opened++;
            } elseif (! $monitor['tripped'] && $open !== null) {
                $this->resolve((string) $open['id'], $nowTs);
                $resolved++;
            }
        }

        return ['opened' => $opened, 'resolved' => $resolved];
    }

    /** @return list<array<string, mixed>> currently open alerts, newest first */
    public function openAlerts(int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));

        return $this->connection->select(
            "SELECT * FROM alerts WHERE status = 'open' ORDER BY opened_at DESC LIMIT " . $limit,
        );
    }

    /**
     * @return list<array{key: string, severity: string, title: string, tripped: bool, value: int, detail: string}>
     */
    private function evaluateAll(int $nowTs): array
    {
        $suspended = $this->count("SELECT COUNT(*) AS c FROM subscriptions WHERE status = 'suspended'");
        $pastDue = $this->count("SELECT COUNT(*) AS c FROM subscriptions WHERE status = 'past_due'");
        $webhookFails = $this->count(
            "SELECT COUNT(*) AS c FROM webhook_deliveries WHERE status = 'failed' AND created_at >= ?",
            [gmdate('Y-m-d H:i:s', $nowTs - 86400)],
        );
        $errors = $this->count(
            'SELECT COUNT(*) AS c FROM error_events WHERE occurred_at >= ?',
            [gmdate('Y-m-d H:i:s', $nowTs - 3600)],
        );

        return [
            ['key' => 'subscriptions.suspended', 'severity' => 'warning', 'title' => 'Suspended subscriptions', 'tripped' => $suspended > 0, 'value' => $suspended, 'detail' => "{$suspended} workspace(s) suspended for non-payment."],
            ['key' => 'subscriptions.past_due', 'severity' => 'warning', 'title' => 'Past-due subscriptions', 'tripped' => $pastDue > 0, 'value' => $pastDue, 'detail' => "{$pastDue} subscription(s) past due."],
            ['key' => 'webhooks.failures_24h', 'severity' => 'warning', 'title' => 'Webhook delivery failures (24h)', 'tripped' => $webhookFails >= 5, 'value' => $webhookFails, 'detail' => "{$webhookFails} failed webhook deliveries in 24h."],
            ['key' => 'errors.spike_1h', 'severity' => 'critical', 'title' => 'Error spike (1h)', 'tripped' => $errors >= 10, 'value' => $errors, 'detail' => "{$errors} errors captured in the last hour."],
        ];
    }

    /** @return array<string, mixed>|null */
    private function openAlertFor(string $key): ?array
    {
        return $this->connection->selectOne(
            "SELECT * FROM alerts WHERE monitor_key = ? AND status = 'open' LIMIT 1",
            [$key],
        );
    }

    /** @param array{key:string,severity:string,title:string,value:int,detail:string} $monitor */
    private function open(array $monitor, int $nowTs): void
    {
        $now = gmdate('Y-m-d H:i:s', $nowTs);
        $this->connection->statement(
            'INSERT INTO alerts (id, monitor_key, severity, status, title, detail, value, opened_at, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [Ulid::generate(), $monitor['key'], $monitor['severity'], 'open', $monitor['title'], $monitor['detail'], $monitor['value'], $now, $now],
        );
    }

    private function resolve(string $alertId, int $nowTs): void
    {
        $this->connection->statement(
            "UPDATE alerts SET status = 'resolved', resolved_at = ? WHERE id = ?",
            [gmdate('Y-m-d H:i:s', $nowTs), $alertId],
        );
    }

    /** @param list<mixed> $args */
    private function count(string $sql, array $args = []): int
    {
        $row = $this->connection->selectOne($sql, $args);

        return (int) ($row['c'] ?? 0);
    }
}
