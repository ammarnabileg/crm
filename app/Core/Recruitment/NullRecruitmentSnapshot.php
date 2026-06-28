<?php

declare(strict_types=1);

namespace HaHireAI\Core\Recruitment;

use HaHireAI\Core\Contracts\RecruitmentSnapshot;

/** Empty snapshot — active when the Recruitment module is disabled. */
final class NullRecruitmentSnapshot implements RecruitmentSnapshot
{
    public function dashboard(string $workspaceId): array
    {
        return [
            'counts' => ['employees' => 0, 'jobs_total' => 0, 'jobs_open' => 0, 'jobs_closed' => 0, 'applicants' => 0, 'needs_attention' => 0],
            'funnel' => ['applications' => 0, 'interviews' => 0, 'offers' => 0, 'hires' => 0],
            'pipeline' => [],
            'today_interviews' => [],
            'recent_jobs' => [],
            'recent_activity' => [],
            'ai_recommendations' => [],
            'health' => ['score' => 0, 'signals' => []],
        ];
    }
}
