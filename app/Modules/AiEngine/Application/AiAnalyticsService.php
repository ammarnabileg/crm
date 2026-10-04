<?php

declare(strict_types=1);

namespace HaHireAI\Modules\AiEngine\Application;

use HaHireAI\Core\Database\Connection;

/**
 * Per-workspace AI analytics: performance, token consumption and usage breakdown
 * computed from `ai_sessions` (workspace-scoped). See docs/AI_ENGINE.md §7.
 */
final class AiAnalyticsService
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /** @return array<string, mixed> */
    public function workspaceAnalytics(string $workspaceId): array
    {
        $totals = $this->connection->selectOne(
            "SELECT COUNT(*) AS runs,
                    COALESCE(SUM(input_tokens + output_tokens), 0) AS tokens,
                    COALESCE(SUM(cost_cents), 0) AS cost_cents,
                    COALESCE(ROUND(AVG(latency_ms)), 0) AS avg_latency_ms,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) AS failed,
                    SUM(CASE WHEN fallback_from IS NOT NULL THEN 1 ELSE 0 END) AS fallbacks
               FROM ai_sessions WHERE workspace_id = ?",
            [$workspaceId],
        );

        return [
            'totals' => [
                'runs' => (int) ($totals['runs'] ?? 0),
                'tokens' => (int) ($totals['tokens'] ?? 0),
                'cost_cents' => (int) ($totals['cost_cents'] ?? 0),
                'avg_latency_ms' => (int) ($totals['avg_latency_ms'] ?? 0),
                'failed' => (int) ($totals['failed'] ?? 0),
                'fallbacks' => (int) ($totals['fallbacks'] ?? 0),
            ],
            'by_capability' => $this->connection->select(
                'SELECT capability, COUNT(*) AS runs, COALESCE(SUM(input_tokens + output_tokens),0) AS tokens, COALESCE(SUM(cost_cents),0) AS cost_cents
                   FROM ai_sessions WHERE workspace_id = ? GROUP BY capability ORDER BY runs DESC',
                [$workspaceId],
            ),
            'by_provider' => $this->connection->select(
                'SELECT provider, COUNT(*) AS runs, COALESCE(SUM(input_tokens + output_tokens),0) AS tokens
                   FROM ai_sessions WHERE workspace_id = ? GROUP BY provider ORDER BY runs DESC',
                [$workspaceId],
            ),
            'recent' => $this->connection->select(
                'SELECT capability, provider, status, (input_tokens + output_tokens) AS tokens, cost_cents, latency_ms, created_at
                   FROM ai_sessions WHERE workspace_id = ? ORDER BY created_at DESC, id DESC LIMIT 15',
                [$workspaceId],
            ),
        ];
    }
}
