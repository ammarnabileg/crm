<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Workspaces\Presentation;

use HaHireAI\Core\Http\Response;
use HaHireAI\Core\View\View;
use HaHireAI\Modules\Authentication\Application\AuthContext;
use HaHireAI\Modules\Memberships\Application\MembershipService;
use HaHireAI\Modules\Workspaces\Application\WorkspaceContext;

/**
 * The workspace dashboard. A user with no workspace sees Create/Join only;
 * otherwise the single dynamic sidebar is rendered from their permissions
 * (docs/DASHBOARD_GUIDE.md, docs/SIDEBAR_MODEL.md).
 */
final class DashboardController
{
    public function __construct(
        private readonly View $view,
        private readonly AuthContext $auth,
        private readonly WorkspaceContext $context,
        private readonly WorkspaceShell $shell,
        private readonly MembershipService $memberships,
    ) {
    }

    public function index(): Response
    {
        if (! $this->auth->check()) {
            return Response::redirect('/login');
        }

        $user = $this->auth->user();

        if (! $this->context->resolve()) {
            return Response::html($this->view->page('workspace.none', [
                'user' => $user,
            ], 'layouts.app', ['user' => $user, 'sidebar' => [], 'workspaceName' => null]));
        }

        $workspaces = $this->memberships->workspacesForUser((string) $user['id']);

        return $this->shell->render($this->context, 'dashboard.index', [
            'user' => $user,
            'workspace' => $this->context->workspace(),
            'workspaces' => $workspaces,
            'currentWorkspaceId' => $this->context->workspaceId(),
            'permissionCount' => count($this->context->permissions()),
            'isSystemOwner' => (int) ($user['is_system_owner'] ?? 0) === 1,
        ]);
    }
}
