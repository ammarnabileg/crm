<?php

declare(strict_types=1);

/*
 * Enabled modules, registered explicitly (no filesystem scanning).
 * Order is not significant — the Module Registry resolves dependency order.
 * See docs/MODULES.md, docs/SERVICE_CONTAINER.md.
 */

return [
    \HaHireAI\Modules\Installer\InstallerModule::class,
    \HaHireAI\Modules\Authentication\AuthenticationModule::class,
    \HaHireAI\Modules\Workspaces\WorkspaceModule::class,
    \HaHireAI\Modules\Memberships\MembershipsModule::class,
    \HaHireAI\Modules\Permissions\PermissionsModule::class,
    \HaHireAI\Modules\Audit\AuditModule::class,
    \HaHireAI\Modules\Search\SearchModule::class,
    \HaHireAI\Modules\Recruitment\RecruitmentModule::class,
    \HaHireAI\Modules\AiEngine\AiModule::class,
    \HaHireAI\Modules\Workflow\WorkflowModule::class,
];
