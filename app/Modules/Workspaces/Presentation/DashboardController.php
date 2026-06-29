<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Workspaces\Presentation;

use HaHireAI\Core\Contracts\CandidateDirectory;
use HaHireAI\Core\Contracts\EntitlementResolver;
use HaHireAI\Core\Contracts\RecruitmentSnapshot;
use HaHireAI\Core\Http\Response;
use HaHireAI\Core\View\View;
use HaHireAI\Modules\Authentication\Application\AuthContext;
use HaHireAI\Core\Contracts\MemberDirectory;
use HaHireAI\Core\Contracts\TaskBoard;
use HaHireAI\Modules\Workspaces\Application\WorkspaceContext;

/**
 * The workspace dashboard. A member sees the permission-driven staff sidebar; a
 * user with no role who has applied somewhere is sent to their Candidate Portal;
 * anyone else is sent to the workspace chooser to create or pick one
 * (docs/DASHBOARD_GUIDE.md, docs/SIDEBAR_MODEL.md).
 */
final class DashboardController
{
    public function __construct(
        private readonly View $view,
        private readonly AuthContext $auth,
        private readonly WorkspaceContext $context,
        private readonly WorkspaceShell $shell,
        private readonly MemberDirectory $memberships,
        private readonly CandidateDirectory $candidates,
        private readonly RecruitmentSnapshot $snapshot,
        private readonly EntitlementResolver $entitlements,
        private readonly TaskBoard $tasks,
    ) {
    }

    public function index(): Response
    {
        if (! $this->auth->check()) {
            return Response::redirect('/login');
        }

        $user = $this->auth->user();
        $isCandidateSomewhere = $this->candidates->workspacesForCandidate((string) $user['id']) !== [];

        // /dashboard is the one context-aware landing. If the user explicitly
        // switched into their candidate context, land them on the candidate
        // experience even when they are also staff somewhere.
        if ($this->auth->contextType() === 'candidate' && $isCandidateSomewhere) {
            return Response::redirect('/my-applications');
        }

        if (! $this->context->resolve()) {
            // No staff role in the active workspace: route candidates to their
            // applications, everyone else to the chooser ("create a workspace").
            if ($isCandidateSomewhere) {
                return Response::redirect('/my-applications');
            }

            return Response::redirect('/workspaces/select');
        }

        $workspaces = $this->memberships->workspacesForUser((string) $user['id']);
        $ws = (string) $this->context->workspaceId();
        $features = $this->entitlements->gateFeatures($ws);

        return $this->shell->render($this->context, 'dashboard.index', [
            'user' => $user,
            'workspace' => $this->context->workspace(),
            'workspaces' => $workspaces,
            'currentWorkspaceId' => $ws,
            'permissionCount' => count($this->context->permissions()),
            'isSystemOwner' => (int) ($user['is_system_owner'] ?? 0) === 1,
            'kpi' => $this->snapshot->dashboard($ws),
            'myTasks' => $this->context->can('task.view') ? $this->tasks->openForUser($ws, (string) $user['id']) : [],
            'canTask' => $this->context->can('task.view'),
            'subscription' => [
                'usable' => $this->entitlements->isUsable($ws),
                'features' => $features === null ? null : count($features),
            ],
            'can' => [
                'job' => $this->context->can('job.create'),
                'candidate' => $this->context->can('candidate.view'),
                'interview' => $this->context->can('interview.view'),
                'pipeline' => $this->context->can('pipeline.view'),
            ],
        ]);
    }
}
