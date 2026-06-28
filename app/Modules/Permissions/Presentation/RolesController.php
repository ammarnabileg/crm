<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Permissions\Presentation;

use HaHireAI\Core\Http\Request;
use HaHireAI\Core\Http\Response;
use HaHireAI\Core\Http\Session;
use HaHireAI\Core\Contracts\AuditRecorder;
use HaHireAI\Modules\Authentication\Application\AuthContext;
use HaHireAI\Modules\Permissions\Application\RoleService;
use HaHireAI\Modules\Permissions\Domain\PermissionCatalog;
use HaHireAI\Modules\Workspaces\Application\WorkspaceContext;
use HaHireAI\Modules\Workspaces\Presentation\WorkspaceShell;

/** The Role Builder UI — roles are data, no hard-coded roles (docs/ROLE_BUILDER.md). */
final class RolesController
{
    public function __construct(
        private readonly WorkspaceShell $shell,
        private readonly WorkspaceContext $context,
        private readonly AuthContext $auth,
        private readonly RoleService $roles,
        private readonly Session $session,
        private readonly AuditRecorder $audit,
    ) {
    }

    public function index(): Response
    {
        if (! $this->auth->check()) {
            return Response::redirect('/login');
        }
        if (! $this->context->resolve()) {
            return Response::redirect('/dashboard');
        }
        if (! $this->context->can('role.view')) {
            return Response::html('<h1>403</h1><p>You do not have permission.</p>', 403);
        }

        return $this->shell->render($this->context, 'roles.index', [
            'roles' => $this->roles->rolesForWorkspace((string) $this->context->workspaceId()),
            'catalog' => $this->groupedWorkspacePermissions(),
            'canCreate' => $this->context->can('role.create'),
            'status' => $this->session->pullFlash('status'),
            'error' => $this->session->pullFlash('error'),
        ]);
    }

    public function create(Request $request): Response
    {
        if (! $this->auth->check()) {
            return Response::redirect('/login');
        }
        if (! $this->context->resolve()) {
            return Response::redirect('/dashboard');
        }
        if (! $this->context->can('role.create') || ! $this->session->verifyCsrf((string) $request->input('_csrf'))) {
            return Response::html('<h1>403</h1><p>You do not have permission.</p>', 403);
        }

        $name = trim((string) $request->input('name', ''));
        if ($name === '') {
            $this->session->flash('error', 'Role name is required.');

            return Response::redirect('/roles');
        }

        /** @var list<string> $keys */
        $keys = (array) $request->input('permissions', []);
        $roleId = $this->roles->createRole((string) $this->context->workspaceId(), $name, $keys);

        $this->audit->record('permissions.role.created', [
            'workspace_id' => $this->context->workspaceId(),
            'actor_user_id' => $this->context->userId(),
            'entity_type' => 'role',
            'entity_id' => $roleId,
            'ip' => $request->server('REMOTE_ADDR'),
            'changes' => ['name' => $name, 'permissions' => count($keys)],
        ]);

        $this->session->flash('status', "Role “{$name}” created with " . count($keys) . ' permission(s).');

        return Response::redirect('/roles');
    }

    /** @return array<string, list<array{key: string, description: string}>> */
    private function groupedWorkspacePermissions(): array
    {
        $grouped = [];

        foreach (PermissionCatalog::all() as $permission) {
            if ($permission['system']) {
                continue;
            }
            $grouped[$permission['category']][] = ['key' => $permission['key'], 'description' => $permission['description']];
        }

        return $grouped;
    }
}
