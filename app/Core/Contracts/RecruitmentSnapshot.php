<?php

declare(strict_types=1);

namespace HaHireAI\Core\Contracts;

/**
 * A workspace's recruitment snapshot for the Executive Dashboard. Lets the
 * Workspaces module render real KPIs without reading Recruitment tables directly
 * (ARCHITECTURE.md §4). Recruitment binds the implementation; Core ships an empty
 * default so the dashboard degrades gracefully when Recruitment is disabled.
 */
interface RecruitmentSnapshot
{
    /**
     * @return array<string, mixed>  counts, funnel, pipeline, today_interviews,
     *         needs_attention, recent_jobs, recent_activity, ai_recommendations, health
     */
    public function dashboard(string $workspaceId): array;
}
