<?php

declare(strict_types=1);

namespace App\Controllers\App;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\ActivityLog;
use App\Models\Role;
use App\Services\Rbac\RbacManager;
use App\Services\Rbac\RoleDirectory;

/**
 * Roles & Permissions (docs/47 RBAC) — the tenant roles in the current workspace
 * and the module-grouped permission matrix that backs them. Reads require
 * roles.view; every write re-checks roles.manage at the top of the action.
 *
 * The matrix reads/writes real role_permissions through RbacManager (the one
 * source of truth), never raw pivot inserts. System roles are protected: they
 * render but cannot be deleted, and the Owner role (`*`, every permission) is
 * shown fully-granted and read-only — its grant is owned by config, not the UI.
 * Everything is tenant-scoped via RoleDirectory; a role from another workspace is
 * never visible or editable.
 */
final class RoleController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless(can('roles.view'), 403);

        $directory = $this->directory();

        return $this->view('app.roles.index', [
            'title'     => 'Roles & Permissions',
            'roles'     => $directory->roles(),
            'canManage' => can('roles.manage'),
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless(can('roles.manage'), 403);

        $directory = $this->directory();

        return $this->view('app.roles.edit', [
            'title'      => 'Create role',
            'role'       => null,
            'modules'    => $directory->permissionCatalogue(),
            'granted'    => [],
            'isOwner'    => false,
            'isSystem'   => false,
            'totalCount' => $directory->totalPermissionCount(),
        ]);
    }

    public function store(Request $request): Response
    {
        abort_unless(can('roles.manage'), 403);

        $data = $this->validate($request, [
            'name'     => 'required|max:120',
            'priority' => 'nullable|integer',
        ]);

        $name = trim((string) $data['name']);
        $workspaceId = (int) tenant()->id();

        $roleId = (int) $this->db()->table('roles')->insertGetId([
            'uuid'         => Role::generateUuid(),
            'workspace_id' => $workspaceId,
            'parent_id'    => null,
            'name'         => $name,
            'slug'         => $this->uniqueSlug($name, $workspaceId),
            'description'  => $this->descriptionInput($request),
            'is_system'    => 0,
            'priority'     => $this->priorityInput($data),
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        $permissionIds = $this->checkedPermissionIds($request);
        (new RbacManager($this->db()))->syncRolePermissions($roleId, $permissionIds);

        ActivityLog::record('roles.created', $workspaceId, (int) auth()->id(), 'Created role "' . $name . '".', ['permissions' => count($permissionIds)], 'role', $roleId);
        $this->withSuccess('Role created.');

        return $this->redirect(url('roles'));
    }

    public function edit(Request $request): Response
    {
        abort_unless(can('roles.manage'), 403);

        $directory = $this->directory();
        $role = $directory->find((int) $request->query('id', 0));
        abort_unless($role !== null, 404);

        $isOwner = $this->isOwner($role);

        return $this->view('app.roles.edit', [
            'title'      => 'Edit role',
            'role'       => $role,
            'modules'    => $directory->permissionCatalogue(),
            'granted'    => $isOwner ? [] : $directory->grantedPermissionIds((int) $role->getKey()),
            'isOwner'    => $isOwner,
            'isSystem'   => (bool) $role->getAttribute('is_system'),
            'totalCount' => $directory->totalPermissionCount(),
        ]);
    }

    public function update(Request $request): Response
    {
        abort_unless(can('roles.manage'), 403);

        $directory = $this->directory();
        $role = $directory->find((int) $request->input('id'));
        abort_unless($role !== null, 404);

        // The Owner role is `*` (every permission) and config-owned: its name and
        // grant are never edited from the UI, so editing it is a guarded no-op.
        if ($this->isOwner($role)) {
            $this->withError('The Owner role grants every permission and cannot be edited.');

            return $this->redirect(url('roles'));
        }

        $data = $this->validate($request, [
            'name'     => 'required|max:120',
            'priority' => 'nullable|integer',
        ]);

        $roleId = (int) $role->getKey();
        $name = trim((string) $data['name']);

        // System roles keep their name/priority/slug (they are referenced by slug);
        // only their permission set may be re-synced. Custom roles update fully.
        $update = ['updated_at' => now()];
        if (! (bool) $role->getAttribute('is_system')) {
            $update['name']        = $name;
            $update['description'] = $this->descriptionInput($request);
            $update['priority']    = $this->priorityInput($data);
        }
        $this->db()->table('roles')->where('id', '=', $roleId)->update($update);

        $permissionIds = $this->checkedPermissionIds($request);
        (new RbacManager($this->db()))->syncRolePermissions($roleId, $permissionIds);

        ActivityLog::record('roles.updated', (int) tenant()->id(), (int) auth()->id(), 'Updated role "' . $name . '".', ['permissions' => count($permissionIds)], 'role', $roleId);
        $this->withSuccess('Role updated.');

        return $this->redirect(url('roles'));
    }

    public function destroy(Request $request): Response
    {
        abort_unless(can('roles.manage'), 403);

        $directory = $this->directory();
        $role = $directory->find((int) $request->input('id'));
        abort_unless($role !== null, 404);

        // System roles (owner/admin/member) are protected — never deletable.
        if ((bool) $role->getAttribute('is_system')) {
            $this->withError('System roles cannot be deleted.');

            return $this->redirect(url('roles'));
        }

        $roleId = (int) $role->getKey();
        $name = (string) $role->getAttribute('name');

        // Detach assignments first so no membership keeps a dangling role, then
        // remove the role and its permission grants.
        $this->db()->table('membership_roles')->where('role_id', '=', $roleId)->delete();
        $this->db()->table('role_permissions')->where('role_id', '=', $roleId)->delete();
        $role->delete();

        ActivityLog::record('roles.deleted', (int) tenant()->id(), (int) auth()->id(), 'Deleted role "' . $name . '".', [], 'role', $roleId);
        $this->withSuccess('Role deleted.');

        return $this->redirect(url('roles'));
    }

    /**
     * The Owner role: the system role whose configured permission set is `*`
     * (every permission). Matched by slug so it stays correct even as the
     * catalogue grows.
     */
    private function isOwner(Role $role): bool
    {
        return (bool) $role->getAttribute('is_system')
            && $role->getAttribute('slug') === 'owner';
    }

    /**
     * The submitted, deduped permission ids — restricted to ids that actually
     * exist in the catalogue so a tampered form can never grant a phantom id.
     *
     * @return int[]
     */
    private function checkedPermissionIds(Request $request): array
    {
        $submitted = array_values(array_unique(array_map('intval', (array) $request->input('permissions', []))));
        if ($submitted === []) {
            return [];
        }

        return array_map('intval', $this->db()->table('permissions')
            ->whereIn('id', $submitted)
            ->pluck('id'));
    }

    private function descriptionInput(Request $request): ?string
    {
        $description = trim((string) $request->input('description', ''));

        return $description !== '' ? mb_substr($description, 0, 255) : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function priorityInput(array $data): int
    {
        $priority = isset($data['priority']) && $data['priority'] !== '' ? (int) $data['priority'] : 0;

        return max(0, min(99, $priority)); // below the system roles (10/80/100)
    }

    /**
     * A workspace-unique slug derived from the name (roles are unique per
     * workspace_id + slug), suffixed on collision.
     */
    private function uniqueSlug(string $name, int $workspaceId): string
    {
        $base = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($name)) ?? '', '-');
        if ($base === '') {
            $base = 'role';
        }

        $slug = $base;
        $i = 2;
        while ($this->db()->table('roles')
            ->where('workspace_id', '=', $workspaceId)
            ->where('slug', '=', $slug)
            ->exists()) {
            $slug = $base . '-' . $i;
            $i++;
        }

        return $slug;
    }

    private function directory(): RoleDirectory
    {
        return new RoleDirectory((int) tenant()->id());
    }

    private function db(): \App\Core\Database
    {
        return app('db');
    }
}
