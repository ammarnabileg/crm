<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Workspaces\Presentation;

use HaHireAI\Core\Http\Response;
use HaHireAI\Core\View\View;
use HaHireAI\Modules\Authentication\Application\AuthContext;
use HaHireAI\Modules\Memberships\Application\MembershipService;
use HaHireAI\Modules\Navigation\Application\SidebarBuilder;
use HaHireAI\Modules\Permissions\Application\Authorizer;

/**
 * The workspace dashboard. Renders the single dynamic sidebar from the current
 * member's permissions; a user with no workspace sees Create/Join only
 * (docs/DASHBOARD_GUIDE.md, docs/SIDEBAR_MODEL.md).
 */
final class DashboardController
{
    public function __construct(
        private readonly View $view,
        private readonly AuthContext $auth,
        private readonly MembershipService $memberships,
        private readonly Authorizer $authorizer,
        private readonly SidebarBuilder $sidebar,
    ) {
    }

    public function index(): Response
    {
        if (! $this->auth->check()) {
            return Response::redirect('/login');
        }

        $user = $this->auth->user();
        $workspaces = $this->memberships->workspacesForUser((string) $user['id']);

        if ($workspaces === []) {
            return Response::html($this->view->page('workspace.none', [
                'user' => $user,
            ], 'layouts.app', ['user' => $user, 'sidebar' => [], 'workspaceName' => null]));
        }

        $current = $this->resolveCurrentWorkspace($workspaces);
        $this->auth->setCurrentWorkspace((string) $current['id']);

        $membership = $this->memberships->find((string) $current['id'], (string) $user['id']);
        $permissions = $membership !== null
            ? $this->authorizer->permissionsForMembership((string) $membership['id'])
            : [];

        $items = $this->sidebar->build('workspace', $permissions);

        return Response::html($this->view->page('dashboard.index', [
            'user' => $user,
            'workspace' => $current,
            'workspaces' => $workspaces,
            'permissionCount' => count($permissions),
            'isSystemOwner' => (int) ($user['is_system_owner'] ?? 0) === 1,
        ], 'layouts.app', [
            'user' => $user,
            'sidebar' => $items,
            'workspaceName' => $current['name'],
        ]));
    }

    /**
     * @param  list<array<string, mixed>>  $workspaces
     * @return array<string, mixed>
     */
    private function resolveCurrentWorkspace(array $workspaces): array
    {
        $currentId = $this->auth->currentWorkspaceId();

        foreach ($workspaces as $workspace) {
            if ((string) $workspace['id'] === $currentId) {
                return $workspace;
            }
        }

        return $workspaces[0];
    }
}
