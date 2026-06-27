<?php

declare(strict_types=1);

namespace App\Services\Observability;

use App\Core\Database;

/**
 * AI Observability (docs/51 §17) — the tenant-scoped dashboard layer over the
 * AiGateway's audit trail. Every model call is recorded in `ai_requests` +
 * `ai_responses`; this service AGGREGATES those rows for the current workspace
 * into a plain dashboard array: request volume, per-provider counts, success /
 * failure / fallback signals, token totals and (where prices are known) an
 * estimated cost.
 *
 * It is read-only and FK-light — it reasons over the append tables exactly as
 * they are written by `AiGateway::record()`, joining `ai_models` only to attach
 * prices. Costs and latency are returned as NULL when the underlying data is
 * unavailable (the offline FakeProvider records no latency, and seed prices are
 * mostly NULL), never as a misleading zero.
 */
final class AiObservability
{
    public function __construct(private readonly Database $db)
    {
    }

    public static function make(): self
    {
        return new self(app('db'));
    }

    /**
     * Aggregate the current tenant's AI request/response audit into dashboard data.
     *
     * @param array{provider?:string, capability?:string, since?:string} $filters
     * @return array<string,mixed>
     */
    public function metrics(array $filters = []): array
    {
        $workspaceId = (int) (tenant()->id() ?? 0);

        $base = $this->db->table('ai_requests')->where('workspace_id', '=', $workspaceId);
        $this->applyFilters($base, $filters);

        $totalRequests = (clone $base)->count();
        $successCount  = (clone $base)->where('status', '=', 'completed')->count();
        $failureCount  = (clone $base)->where('status', '=', 'failed')->count();

        return [
            'total_requests'          => $totalRequests,
            'by_provider'             => $this->byProvider($filters, $workspaceId),
            'success_count'           => $successCount,
            'failure_count'           => $failureCount,
            'success_rate'            => $totalRequests > 0
                ? round($successCount / $totalRequests, 4)
                : 0.0,
            'avg_prompt_tokens'       => $this->avgPromptTokens($filters, $workspaceId),
            'total_prompt_tokens'     => (int) (clone $base)->sum('prompt_tokens'),
            'total_completion_tokens' => $this->totalResponseTokens('completion_tokens', $filters, $workspaceId),
            'avg_latency_ms'          => $this->avgLatencyMs($filters, $workspaceId),
            // Fallback approximation (§17): a failed attempt is one the gateway
            // had to fall back FROM, so failures stand in for the fallback count.
            'fallback_count'          => $failureCount,
            'estimated_cost'          => $this->estimatedCost($filters, $workspaceId),
        ];
    }

    /**
     * Per-provider request counts (provider key => count). Joins `ai_providers`
     * so the human-readable provider key is used, not the numeric id.
     *
     * @param array<string,mixed> $filters
     * @return array<string,int>
     */
    private function byProvider(array $filters, int $workspaceId): array
    {
        [$where, $bindings] = $this->whereClause($filters, $workspaceId, 'r');

        $rows = $this->db->select(
            "SELECT COALESCE(p.`key`, 'unknown') AS provider, COUNT(*) AS total
             FROM `ai_requests` r
             LEFT JOIN `ai_providers` p ON p.`id` = r.`provider_id`
             {$where}
             GROUP BY provider",
            $bindings
        );

        $out = [];
        foreach ($rows as $row) {
            $out[(string) $row['provider']] = (int) $row['total'];
        }

        return $out;
    }

    /** @param array<string,mixed> $filters */
    private function avgPromptTokens(array $filters, int $workspaceId): ?float
    {
        [$where, $bindings] = $this->whereClause($filters, $workspaceId, 'r');

        $avg = $this->db->scalar(
            "SELECT AVG(r.`prompt_tokens`) FROM `ai_requests` r {$where}",
            $bindings
        );

        return $avg !== null ? round((float) $avg, 2) : null;
    }

    /**
     * Sum a numeric column on the tenant's `ai_responses` (joined to the matching
     * `ai_requests` so the same filters apply).
     *
     * @param array<string,mixed> $filters
     */
    private function totalResponseTokens(string $column, array $filters, int $workspaceId): int
    {
        [$where, $bindings] = $this->whereClause($filters, $workspaceId, 'r');

        $sum = $this->db->scalar(
            "SELECT COALESCE(SUM(resp.`{$column}`), 0)
             FROM `ai_responses` resp
             INNER JOIN `ai_requests` r ON r.`id` = resp.`request_id`
             {$where}",
            $bindings
        );

        return (int) $sum;
    }

    /**
     * Average provider latency, or NULL when no response carries a latency_ms
     * (the offline sandbox records none).
     *
     * @param array<string,mixed> $filters
     */
    private function avgLatencyMs(array $filters, int $workspaceId): ?float
    {
        [$where, $bindings] = $this->whereClause($filters, $workspaceId, 'r');

        $avg = $this->db->scalar(
            "SELECT AVG(resp.`latency_ms`)
             FROM `ai_responses` resp
             INNER JOIN `ai_requests` r ON r.`id` = resp.`request_id`
             {$where} AND resp.`latency_ms` IS NOT NULL",
            $bindings
        );

        return $avg !== null ? round((float) $avg, 2) : null;
    }

    /**
     * Estimated spend = SUM over responses of token-priced cost, using the
     * model's per-1k input/output prices when known. Returns NULL when NO
     * priced row contributed (prices are mostly NULL in seed data), so an
     * "unknown" cost is never shown as 0.
     *
     * @param array<string,mixed> $filters
     */
    private function estimatedCost(array $filters, int $workspaceId): ?float
    {
        [$where, $bindings] = $this->whereClause($filters, $workspaceId, 'r');

        $row = $this->db->selectOne(
            "SELECT
                SUM(
                    (COALESCE(resp.`prompt_tokens`, 0) / 1000) * COALESCE(m.`input_price_per_1k`, 0)
                    + (COALESCE(resp.`completion_tokens`, 0) / 1000) * COALESCE(m.`output_price_per_1k`, 0)
                ) AS est_cost,
                SUM(CASE WHEN m.`input_price_per_1k` IS NOT NULL OR m.`output_price_per_1k` IS NOT NULL THEN 1 ELSE 0 END) AS priced
             FROM `ai_responses` resp
             INNER JOIN `ai_requests` r ON r.`id` = resp.`request_id`
             LEFT JOIN `ai_models` m ON m.`id` = resp.`model_id`
             {$where}",
            $bindings
        );

        if ($row === null || (int) ($row['priced'] ?? 0) === 0) {
            return null;
        }

        return round((float) ($row['est_cost'] ?? 0), 6);
    }

    /**
     * Apply optional filters to a QueryBuilder over `ai_requests`.
     *
     * @param array<string,mixed> $filters
     */
    private function applyFilters(object $query, array $filters): void
    {
        if (! empty($filters['capability'])) {
            $query->where('capability', '=', (string) $filters['capability']);
        }
        if (! empty($filters['since'])) {
            $query->where('created_at', '>=', (string) $filters['since']);
        }
        if (! empty($filters['provider'])) {
            $providerId = $this->db->table('ai_providers')
                ->where('key', '=', (string) $filters['provider'])
                ->value('id');
            $query->where('provider_id', '=', $providerId !== null ? (int) $providerId : 0);
        }
    }

    /**
     * Build a parameterised WHERE clause (mirrors applyFilters) for the raw
     * aggregate queries, with `$alias` as the `ai_requests` table alias.
     *
     * @param array<string,mixed> $filters
     * @return array{0:string,1:array<int,mixed>}
     */
    private function whereClause(array $filters, int $workspaceId, string $alias): array
    {
        $conditions = ["{$alias}.`workspace_id` = ?"];
        $bindings = [$workspaceId];

        if (! empty($filters['capability'])) {
            $conditions[] = "{$alias}.`capability` = ?";
            $bindings[] = (string) $filters['capability'];
        }
        if (! empty($filters['since'])) {
            $conditions[] = "{$alias}.`created_at` >= ?";
            $bindings[] = (string) $filters['since'];
        }
        if (! empty($filters['provider'])) {
            $providerId = $this->db->table('ai_providers')
                ->where('key', '=', (string) $filters['provider'])
                ->value('id');
            $conditions[] = "{$alias}.`provider_id` = ?";
            $bindings[] = $providerId !== null ? (int) $providerId : 0;
        }

        return ['WHERE ' . implode(' AND ', $conditions), $bindings];
    }
}
