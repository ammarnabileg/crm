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
    ) {
    }

    public function index(): Response
    {
        if (! $this->auth->check()) {
            return Response::redirect('/login');
        }

        $user = $this->auth->user();

        if (! $this->context->resolve()) {
            // No staff role anywhere: route candidates to their portal, everyone
            // else to the chooser (which offers "create your workspace").
            if ($this->candidates->workspacesForCandidate((string) $user['id']) !== []) {
                return Response::redirect('/portal');
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
