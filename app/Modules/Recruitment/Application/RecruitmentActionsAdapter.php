<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Recruitment\Application;

use HaHireAI\Core\Contracts\RecruitmentActions;

/**
 * Bridges the Core RecruitmentActions contract to the ApplicationService, so the
 * Workflow Engine can advance or update an application as an automation step
 * without depending on Recruitment internals (ARCHITECTURE.md §4).
 */
final class RecruitmentActionsAdapter implements RecruitmentActions
{
    public function __construct(private readonly ApplicationService $applications)
    {
    }

    public function moveApplicationStage(string $workspaceId, string $applicationId, string $toStageId, ?string $actorUserId = null): void
    {
        $this->applications->moveStage($workspaceId, $applicationId, $toStageId, $actorUserId);
    }

    public function setApplicationStatus(string $workspaceId, string $applicationId, string $status, ?string $actorUserId = null): void
    {
        $this->applications->setStatus($workspaceId, $applicationId, $status, $actorUserId);
    }
}
