<?php

declare(strict_types=1);

namespace HaHireAI\Core\Workflow;

use HaHireAI\Core\Contracts\RecruitmentActions;

/** No-op recruitment actions — active when the Recruitment module is disabled. */
final class NullRecruitmentActions implements RecruitmentActions
{
    public function moveApplicationStage(string $workspaceId, string $applicationId, string $toStageId, ?string $actorUserId = null): void
    {
    }

    public function setApplicationStatus(string $workspaceId, string $applicationId, string $status, ?string $actorUserId = null): void
    {
    }
}
