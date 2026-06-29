<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Permissions\Presentation;

use HaHireAI\Core\Contracts\AuditRecorder;
use HaHireAI\Core\Http\Request;
use HaHireAI\Core\Http\Response;
use HaHireAI\Core\Http\Session;
use HaHireAI\Modules\Authentication\Application\AuthContext;
use HaHireAI\Modules\Permissions\Application\PlatformRoleService;
use HaHireAI\Modules\Workspaces\Application\PlatformContext;
use HaHireAI\Modules\Workspaces\Presentation\PlatformShell;

/**
 * Platform Roles & Permissions (System Owner): build roles from the system.*
 * catalog and assign them to users, creating granular "site managers". Gated by
 * `system.roles.manage` (docs/PERMISSION_MODEL.md §6).
 */
final class PlatformRolesController
{
    public function __construct(
        private readonly PlatformShell $shell,
        private readonly PlatformContext $context,
        private readonly AuthContext $auth,
        private readonly PlatformRoleService $roles,
        private readonly Session $session,
        private readonly AuditRecorder $audit,
    ) {
    }

    public function index(): Response
    {
        if (($r = $this->gate()) !== null) {
            return $r;
        }

        return $this->shell->render($this->context, 'admin.platform_roles', [
            'roles' => $this->roles->allRoles(),
            'catalog' => $this->roles->systemPermissions(),
            'users' => $this->roles->assignableUsers(),
            'status' => $this->session->pullFlash('status'),
            'error' => $this->session->pullFlash('error'),
        ]);
    }

    public function create(Request $request): Response
    {
        if (($r = $this->gate($request)) !== null) {
            return $r;
        }

        $name = trim((string) $request->input('name', ''));
        if ($name === '') {
            $this->session->flash('error', 'Role name is required.');

            return Response::redirect('/admin/roles');
        }

        $id = $this->roles->create($name, trim((string) $request->input('description', '')) ?: null, $this->permissionKeys($request));
        $this->audit->record('platform.role.created', [
            'actor_user_id' => $this->auth->id(), 'entity_type' => 'platform_role', 'entity_id' => $id,
            'changes' => ['name' => $name],
        ]);
        $this->session->flash('status', "Role “{$name}” created.");

        return Response::redirect('/admin/roles');
    }

    public function update(Request $request, string $id): Response
    {
        if (($r = $this->gate($request)) !== null) {
            return $r;
        }
        if ($this->roles->find($id) === null) {
            $this->session->flash('error', 'Role not found.');

            return Response::redirect('/admin/roles');
        }

        $this->roles->update($id, trim((string) $request->input('name', 'Role')) ?: 'Role', trim((string) $request->input('description', '')) ?: null, $this->permissionKeys($request));
        $this->audit->record('platform.role.updated', [
            'actor_user_id' => $this->auth->id(), 'entity_type' => 'platform_role', 'entity_id' => $id,
        ]);
        $this->session->flash('status', 'Role updated.');

        return Response::redirect('/admin/roles');
    }

    public function delete(Request $request, string $id): Response
    {
        if (($r = $this->gate($request)) !== null) {
            return $r;
        }

        $this->roles->delete($id);
        $this->audit->record('platform.role.deleted', [
            'actor_user_id' => $this->auth->id(), 'entity_type' => 'platform_role', 'entity_id' => $id,
        ]);
        $this->session->flash('status', 'Role deleted.');

        return Response::redirect('/admin/roles');
    }

    public function assign(Request $request, string $id): Response
    {
        if (($r = $this->gate($request)) !== null) {
            return $r;
        }

        $userId = trim((string) $request->input('user_id', ''));
        if ($userId !== '') {
            $this->roles->assignUser($id, $userId);
            $this->audit->record('platform.role.assigned', [
                'actor_user_id' => $this->auth->id(), 'entity_type' => 'platform_role', 'entity_id' => $id,
                'changes' => ['user_id' => $userId],
            ]);
            $this->session->flash('status', 'Role assigned.');
        }

        return Response::redirect('/admin/roles');
    }

    public function unassign(Request $request, string $id): Response
    {
        if (($r = $this->gate($request)) !== null) {
            return $r;
        }

        $userId = trim((string) $request->input('user_id', ''));
        if ($userId !== '') {
            $this->roles->unassignUser($id, $userId);
            $this->audit->record('platform.role.unassigned', [
                'actor_user_id' => $this->auth->id(), 'entity_type' => 'platform_role', 'entity_id' => $id,
                'changes' => ['user_id' => $userId],
            ]);
            $this->session->flash('status', 'Role unassigned.');
        }

        return Response::redirect('/admin/roles');
    }

    /** @return list<string> selected permission keys from the form */
    private function permissionKeys(Request $request): array
    {
        return array_values(array_filter(array_map('strval', (array) $request->input('permissions', []))));
    }

    private function gate(?Request $request = null): ?Response
    {
        if (! $this->auth->check()) {
            return Response::redirect('/login');
        }
        if (! $this->context->resolve()) {
            return Response::html('<h1>403</h1><p>Platform access requires a System Owner.</p>', 403);
        }
        if (! $this->context->can('system.roles.manage')) {
            return Response::html('<h1>403</h1><p>You do not have permission.</p>', 403);
        }
        if ($request !== null && ! $this->session->verifyCsrf((string) $request->input('_csrf'))) {
            return Response::html('<h1>419</h1><p>Security check failed.</p>', 419);
        }

        return null;
    }
}
