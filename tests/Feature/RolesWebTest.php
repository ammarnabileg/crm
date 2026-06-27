<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Controllers\App\RoleController;
use App\Core\Request;
use App\Models\Role;
use App\Services\Rbac\RbacManager;
use App\Services\Tenancy\WorkspaceService;
use App\Models\User;
use Tests\TestCase;

/**
 * Roles & Permissions web layer (docs/47 RBAC) — the role list renders end to end
 * (controller -> directory -> view -> layout) with real data, creating a role
 * persists it tenant-scoped with its chosen permissions, updating a role re-syncs
 * the role_permissions through RbacManager (add + remove), system roles are
 * protected from deletion, and the matrix is grouped by system_modules. Reads are
 * gated by roles.view and writes by roles.manage, enforced inside the controller.
 *
 * Fixtures are built fresh inside a rolled-back transaction (mirrors
 * MembersWebTest) so the suite never depends on or mutates seeded data: a
 * brand-new workspace gives its creator the Owner role (every tenant permission)
 * and the default tenant role set, and the creator is authenticated via the
 * session so the RBAC gates resolve for real.
 */
return new class extends TestCase {
    protected bool $useDatabaseTransaction = true;

    private int $workspaceId = 0;
    private int $ownerId = 0;

    public function setUp(): void
    {
        $owner = User::create([
            'name'           => 'Workspace Owner',
            'email'          => 'owner-' . uniqid() . '@roles.test',
            'password'       => 'x',
            'user_status_id' => lookup_id('user_status', 'active'),
        ]);
        $this->ownerId = (int) $owner->getKey();

        $workspace = (new WorkspaceService())->create($owner, 'Roles Test Co');
        $this->workspaceId = (int) $workspace->getKey();
        tenant()->setById($this->workspaceId);

        // Authenticate the owner so the controller's can() gates resolve.
        session()->put((string) config('auth.session_key', 'auth_user_id'), $this->ownerId);
    }

    /**
     * Reset the AuthManager resolution + the AccessControl per-request cache on the
     * EXISTING singletons (preserving registered policy gates) so later
     * session-auth tests re-resolve cleanly — test isolation, no leak.
     */
    public function tearDown(): void
    {
        session()->forget((string) config('auth.session_key', 'auth_user_id'));

        $auth = app('auth');
        $r = new \ReflectionObject($auth);
        foreach (['resolved' => false, 'user' => null] as $prop => $value) {
            if ($r->hasProperty($prop)) {
                $p = $r->getProperty($prop);
                $p->setAccessible(true);
                $p->setValue($auth, $value);
            }
        }

        $access = app('access');
        $ra = new \ReflectionObject($access);
        if ($ra->hasProperty('cache')) {
            $p = $ra->getProperty('cache');
            $p->setAccessible(true);
            $p->setValue($access, []);
        }
    }

    private function request(array $query = [], array $body = []): Request
    {
        $method = $body === [] ? 'GET' : 'POST';
        $req = new Request($query, $body, ['REQUEST_METHOD' => $method, 'REQUEST_URI' => '/'], [], []);
        app()->instance('request', $req); // the layout calls request()->path()

        return $req;
    }

    private function roleId(string $slug): int
    {
        return (int) app('db')->table('roles')
            ->where('workspace_id', '=', $this->workspaceId)
            ->where('slug', '=', $slug)
            ->value('id');
    }

    private function permissionId(string $key): int
    {
        return (int) app('db')->table('permissions')->where('key', '=', $key)->value('id');
    }

    public function test_index_renders_the_workspace_roles_including_system_roles(): void
    {
        $res = (new RoleController())->index($this->request());
        $this->assertSame(200, $res->getStatus());

        $content = $res->getContent();
        $this->assertTrue(str_contains($content, 'Roles &amp; Permissions') || str_contains($content, 'Roles & Permissions'));
        // The seeded system roles are listed by name.
        $this->assertTrue(str_contains($content, 'Owner'));
        $this->assertTrue(str_contains($content, 'Administrator'));
        $this->assertTrue(str_contains($content, 'Member'));
        // Owner renders as the all-permissions role, not an editable one.
        $this->assertTrue(str_contains($content, 'All permissions'));
    }

    public function test_create_persists_role_with_chosen_permissions(): void
    {
        $viewPerm   = $this->permissionId('members.view');
        $invitePerm = $this->permissionId('members.invite');
        $this->assertTrue($viewPerm > 0 && $invitePerm > 0);

        $res = (new RoleController())->store($this->request([], [
            'name'        => 'Recruiter',
            'priority'    => '25',
            'description' => 'Runs the hiring pipeline',
            'permissions' => [(string) $viewPerm, (string) $invitePerm],
        ]));
        $this->assertSame(302, $res->getStatus());

        // Persisted, tenant-scoped, non-system.
        $row = app('db')->table('roles')
            ->where('workspace_id', '=', $this->workspaceId)
            ->where('name', '=', 'Recruiter')
            ->first();
        $this->assertNotNull($row);
        $this->assertSame(0, (int) $row['is_system']);
        $this->assertSame(25, (int) $row['priority']);

        // The chosen permissions are written to role_permissions.
        $granted = array_map('intval', app('db')->table('role_permissions')
            ->where('role_id', '=', (int) $row['id'])
            ->pluck('permission_id'));
        sort($granted);
        $expected = [$viewPerm, $invitePerm];
        sort($expected);
        $this->assertSame($expected, $granted);
    }

    public function test_update_syncs_permissions_add_and_remove(): void
    {
        // A custom role seeded with members.view only.
        $viewPerm   = $this->permissionId('members.view');
        $invitePerm = $this->permissionId('members.invite');
        $roleId = (int) app('db')->table('roles')->insertGetId([
            'uuid'         => Role::generateUuid(),
            'workspace_id' => $this->workspaceId,
            'name'         => 'Coordinator',
            'slug'         => 'coordinator',
            'is_system'    => 0,
            'priority'     => 20,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
        (new RbacManager(app('db')))->syncRolePermissions($roleId, [$viewPerm]);

        // Update: drop members.view, add members.invite.
        $res = (new RoleController())->update($this->request([], [
            'id'          => (string) $roleId,
            'name'        => 'Coordinator',
            'priority'    => '20',
            'permissions' => [(string) $invitePerm],
        ]));
        $this->assertSame(302, $res->getStatus());

        $granted = array_map('intval', app('db')->table('role_permissions')
            ->where('role_id', '=', $roleId)
            ->pluck('permission_id'));
        // Added invite, removed view — a real sync.
        $this->assertTrue(in_array($invitePerm, $granted, true));
        $this->assertFalse(in_array($viewPerm, $granted, true));
    }

    public function test_system_role_cannot_be_deleted(): void
    {
        $adminRoleId = $this->roleId('admin');
        $this->assertTrue($adminRoleId > 0);

        $res = (new RoleController())->destroy($this->request([], ['id' => (string) $adminRoleId]));
        // Guarded no-op: redirects back, the row still exists.
        $this->assertSame(302, $res->getStatus());

        $stillExists = app('db')->table('roles')
            ->where('id', '=', $adminRoleId)
            ->whereNull('deleted_at')
            ->exists();
        $this->assertTrue($stillExists);
    }

    public function test_non_system_role_can_be_deleted(): void
    {
        $roleId = (int) app('db')->table('roles')->insertGetId([
            'uuid'         => Role::generateUuid(),
            'workspace_id' => $this->workspaceId,
            'name'         => 'Temp Role',
            'slug'         => 'temp-role',
            'is_system'    => 0,
            'priority'     => 5,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        $res = (new RoleController())->destroy($this->request([], ['id' => (string) $roleId]));
        $this->assertSame(302, $res->getStatus());

        $exists = app('db')->table('roles')->where('id', '=', $roleId)->exists();
        $this->assertFalse($exists);
    }

    public function test_role_from_another_workspace_is_not_editable(): void
    {
        // A second workspace + one of its roles. The acting owner has no rights
        // there, so the edit must 404 (tenant scope), never expose the foreign role.
        $other = User::create([
            'name'           => 'Other Owner',
            'email'          => 'other-' . uniqid() . '@roles.test',
            'password'       => 'x',
            'user_status_id' => lookup_id('user_status', 'active'),
        ]);
        $otherWorkspace = (new WorkspaceService())->create($other, 'Other Co');
        $foreignRoleId = (int) app('db')->table('roles')
            ->where('workspace_id', '=', (int) $otherWorkspace->getKey())
            ->where('slug', '=', 'member')
            ->value('id');
        $this->assertTrue($foreignRoleId > 0);

        // Re-assert the acting tenant (creating the other workspace switched it).
        tenant()->setById($this->workspaceId);

        $threw = false;
        try {
            (new RoleController())->edit($this->request(['id' => (string) $foreignRoleId]));
        } catch (\Throwable $e) {
            $threw = true; // abort(404) throws an HttpException
        }
        $this->assertTrue($threw);
    }

    public function test_matrix_is_grouped_by_system_modules(): void
    {
        $res = (new RoleController())->create($this->request());
        $this->assertSame(200, $res->getStatus());
        $content = $res->getContent();

        // Each module that owns a permission appears as a section heading. The
        // catalogue includes Members, Roles and Billing modules at minimum.
        foreach (['Members', 'Roles', 'Billing'] as $moduleLabel) {
            $this->assertTrue(str_contains($content, $moduleLabel), "Missing module section: {$moduleLabel}");
        }
        // And individual permissions render under them.
        $this->assertTrue(str_contains($content, 'members.view'));
        $this->assertTrue(str_contains($content, 'roles.manage'));
    }
};
