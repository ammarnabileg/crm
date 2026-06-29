<?php

declare(strict_types=1);

namespace HaHireAI\Core\Contracts;

/**
 * The write surface of recruitment that the Workflow Engine uses to act on an
 * application as an automation step — move it along the pipeline or set its status
 * — never the concrete `Recruitment\Application\ApplicationService`
 * (ARCHITECTURE.md §4). Bound to a thin adapter at boot; Core ships a no-op default
 * so workflows degrade gracefully when Recruitment is disabled.
 */
interface RecruitmentActions
{
    /** Move an application to another pipeline stage. */
    public function moveApplicationStage(string $workspaceId, string $applicationId, string $toStageId, ?string $actorUserId = null): void;

    /** Set an application's status (e.g. shortlisted, rejected, hired). */
    public function setApplicationStatus(string $workspaceId, string $applicationId, string $status, ?string $actorUserId = null): void;
}
