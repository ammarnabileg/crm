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
            'roles' => $this->roles->rolesForWorkspaceWithUsage((string) $this->context->workspaceId()),
            'catalog' => $this->groupedWorkspacePermissions(),
            'canCreate' => $this->context->can('role.create'),
            'canClone' => $this->context->can('role.clone'),
            'canDelete' => $this->context->can('role.delete'),
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

    public function clone(Request $request, string $roleId): Response
    {
        if (! $this->auth->check()) {
            return Response::redirect('/login');
        }
        if (! $this->context->resolve()) {
            return Response::redirect('/dashboard');
        }
        if (! $this->context->can('role.clone') || ! $this->session->verifyCsrf((string) $request->input('_csrf'))) {
            return Response::html('<h1>403</h1><p>You do not have permission.</p>', 403);
        }

        $workspaceId = (string) $this->context->workspaceId();
        $source = $this->roles->findRole($workspaceId, $roleId);
        if ($source === null) {
            $this->session->flash('error', 'Role not found in this workspace.');

            return Response::redirect('/roles');
        }

        $newName = $this->uniqueCopyName($workspaceId, (string) $source['name']);
        $newId = $this->roles->cloneRole($workspaceId, $roleId, $newName);

        $this->audit->record('permissions.role.cloned', [
            'workspace_id' => $workspaceId,
            'actor_user_id' => $this->context->userId(),
            'entity_type' => 'role',
            'entity_id' => $newId,
            'ip' => $request->server('REMOTE_ADDR'),
            'changes' => ['source_role_id' => $roleId, 'name' => $newName],
        ]);

        $this->session->flash('status', "Role cloned as “{$newName}”.");

        return Response::redirect('/roles');
    }

    public function delete(Request $request, string $roleId): Response
    {
        if (! $this->auth->check()) {
            return Response::redirect('/login');
        }
        if (! $this->context->resolve()) {
            return Response::redirect('/dashboard');
        }
        if (! $this->context->can('role.delete') || ! $this->session->verifyCsrf((string) $request->input('_csrf'))) {
            return Response::html('<h1>403</h1><p>You do not have permission.</p>', 403);
        }

        $workspaceId = (string) $this->context->workspaceId();
        $role = $this->roles->findRole($workspaceId, $roleId);
        if ($role === null) {
            $this->session->flash('error', 'Role not found in this workspace.');

            return Response::redirect('/roles');
        }

        $inUse = $this->roles->usageCount($roleId);
        if ($inUse > 0) {
            $this->session->flash('error', "“{$role['name']}” is assigned to {$inUse} member(s). Reassign them before deleting it.");

            return Response::redirect('/roles');
        }

        $this->roles->deleteRole($workspaceId, $roleId);

        $this->audit->record('permissions.role.deleted', [
            'workspace_id' => $workspaceId,
            'actor_user_id' => $this->context->userId(),
            'entity_type' => 'role',
            'entity_id' => $roleId,
            'ip' => $request->server('REMOTE_ADDR'),
            'changes' => ['name' => $role['name']],
        ]);

        $this->session->flash('status', "Role “{$role['name']}” deleted.");

        return Response::redirect('/roles');
    }

    /** Pick a non-colliding "(copy)" name so the unique (workspace, name) index is never violated. */
    private function uniqueCopyName(string $workspaceId, string $base): string
    {
        $existing = array_map(
            static fn (array $r): string => (string) $r['name'],
            $this->roles->rolesForWorkspace($workspaceId),
        );

        $candidate = $base . ' (copy)';
        $n = 2;
        while (in_array($candidate, $existing, true)) {
            $candidate = $base . " (copy {$n})";
            $n++;
        }

        return $candidate;
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
