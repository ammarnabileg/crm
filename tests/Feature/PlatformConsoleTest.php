<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Controllers\System\PlatformController;
use App\Core\Request;
use App\Models\User;
use App\Services\Platform\PlatformDirectory;
use App\Services\Rbac\RbacManager;
use App\Services\Tenancy\WorkspaceService;
use Tests\TestCase;

/**
 * Super-Admin Platform Console (docs/47 RBAC bible — cross-tenant control panel).
 *
 * A super admin manages every workspace and every user from above the tenant
 * boundary. These tests prove the gate and the cross-tenant reach end to end:
 *   (a) a super admin sees ALL workspaces, including ones they don't belong to,
 *   (b) a non-super-admin is refused (403) by the controller,
 *   (c) suspending a workspace flips its status AND writes an audit row,
 *   (d) the users list is cross-tenant,
 *   (e) suspend/activate a user works.
 *
 * Fixtures are built fresh inside a rolled-back transaction (mirrors
 * MembersWebTest): a brand-new owner+workspace gives the actor a real, resolved
 * identity; the actor is then granted the platform super-admin role (a global
 * role with no workspace_id) so AccessControl's super-admin bypass grants
 * system.manage. A SECOND workspace under a DIFFERENT owner exists only to prove
 * the actor sees beyond their own tenant — the actor's own grants are never
 * stripped.
 */
return new class extends TestCase {
    protected bool $useDatabaseTransaction = true;

    private int $adminId = 0;
    private int $adminWorkspaceId = 0;

    private int $otherOwnerId = 0;
    private int $otherWorkspaceId = 0;
    private string $otherWorkspaceName = '';
    private string $otherOwnerEmail = '';

    private int $plainUserId = 0;

    public function setUp(): void
    {
        // Begin from a clean auth/RBAC state. The runner skips tearDown() when a
        // test throws (e.g. an assertion failure), so a prior test could leave the
        // auth singleton resolved to a stale user; reset up-front so every test is
        // independent (Wave-1 gotcha: a leaked resolved user silently 403s).
        $this->resetAuthState();

        // --- The acting super admin (own workspace gives a real identity). -----
        $admin = User::create([
            'name'           => 'Platform Admin',
            'email'          => 'admin-' . uniqid() . '@platform.test',
            'password'       => 'x',
            'user_status_id' => lookup_id('user_status', 'active'),
        ]);
        $this->adminId = (int) $admin->getKey();
        $this->adminWorkspaceId = (int) (new WorkspaceService())->create($admin, 'Admin Home Co')->getKey();

        // Grant the platform super-admin role (global, workspace_id IS NULL) so
        // can('system.manage') resolves via the super-admin bypass.
        $rbac = new RbacManager(app('db'));
        $rbac->assignGlobalRole($this->adminId, $rbac->ensureSuperAdminRole());

        // --- A second, foreign workspace the admin does NOT belong to. ---------
        $this->otherOwnerEmail = 'other-' . uniqid() . '@platform.test';
        $otherOwner = User::create([
            'name'           => 'Foreign Owner',
            'email'          => $this->otherOwnerEmail,
            'password'       => 'x',
            'user_status_id' => lookup_id('user_status', 'active'),
        ]);
        $this->otherOwnerId = (int) $otherOwner->getKey();
        $this->otherWorkspaceName = 'Foreign Co ' . uniqid();
        $this->otherWorkspaceId = (int) (new WorkspaceService())->create($otherOwner, $this->otherWorkspaceName)->getKey();

        // --- A plain user with NO roles in the admin's tenant. ----------------
        // Used for the 403 case: an Owner can't be the negative subject because the
        // Owner role's wildcard ('*') grants every permission INCLUDING system.manage,
        // so an Owner legitimately passes the gate. A roleless user is the true
        // "not a super admin, no system.manage" subject.
        $plain = User::create([
            'name'           => 'Plain User',
            'email'          => 'plain-' . uniqid() . '@platform.test',
            'password'       => 'x',
            'user_status_id' => lookup_id('user_status', 'active'),
        ]);
        $this->plainUserId = (int) $plain->getKey();

        // Authenticate the admin AND set the admin's own tenant. The admin is not
        // a member of the foreign workspace — the console must still surface it.
        tenant()->setById($this->adminWorkspaceId);
        session()->put((string) config('auth.session_key', 'auth_user_id'), $this->adminId);
    }

    /**
     * Reset the auth resolution + AccessControl per-request cache on the EXISTING
     * singletons (preserving registered policy gates) so later session-auth tests
     * re-resolve cleanly — test isolation, no leak.
     */
    public function tearDown(): void
    {
        session()->forget((string) config('auth.session_key', 'auth_user_id'));
        session()->forget('platform_impersonator_id');
        $this->resetAuthState();
    }

    private function request(array $query = [], array $body = []): Request
    {
        $method = $body === [] ? 'GET' : 'POST';
        $req = new Request($query, $body, ['REQUEST_METHOD' => $method, 'REQUEST_URI' => '/'], [], []);
        app()->instance('request', $req); // the layout calls request()->path()

        return $req;
    }

    public function test_super_admin_sees_every_workspace_including_foreign_ones(): void
    {
        $res = (new PlatformController())->workspaces($this->request());
        $this->assertSame(200, $res->getStatus());

        $content = $res->getContent();
        // The admin's own workspace AND the foreign one (which they don't belong
        // to) must both be listed — proof the query is not tenant-scoped.
        $this->assertTrue(str_contains($content, 'Admin Home Co'));
        $this->assertTrue(str_contains($content, $this->otherWorkspaceName));
        $this->assertTrue(str_contains($content, $this->otherOwnerEmail));
    }

    public function test_service_returns_foreign_workspace_for_super_admin(): void
    {
        $rows = (new PlatformDirectory())->workspaces();
        $ids = array_map(static fn (array $r): int => (int) $r['id'], $rows);

        // Both workspaces present regardless of the active tenant.
        $this->assertTrue(in_array($this->adminWorkspaceId, $ids, true));
        $this->assertTrue(in_array($this->otherWorkspaceId, $ids, true));
    }

    public function test_non_super_admin_gets_403_from_workspaces(): void
    {
        // The plain user holds no roles in the active tenant and is not a super
        // admin, so system.manage is denied. Re-auth as them WITHOUT touching the
        // admin's grants (act on a second identity — Wave-1 gotcha).
        session()->put((string) config('auth.session_key', 'auth_user_id'), $this->plainUserId);
        tenant()->setById($this->adminWorkspaceId);

        // Flush the cached admin identity so auth() re-resolves to the plain user.
        $this->resetAuthState();
        // Re-establish the plain user's session (resetAuthState only clears caches).
        session()->put((string) config('auth.session_key', 'auth_user_id'), $this->plainUserId);

        $this->assertFalse(can('system.manage'), 'A roleless user must not hold system.manage.');

        $threw = false;
        try {
            (new PlatformController())->workspaces($this->request());
        } catch (\App\Core\Exceptions\HttpException $e) {
            $threw = true;
            $this->assertSame(403, $e->getStatusCode());
        }
        $this->assertTrue($threw, 'A non-super-admin should be refused with a 403.');
    }

    public function test_suspend_workspace_flips_status_and_writes_audit_log(): void
    {
        $before = app('db')->table('activity_logs')
            ->where('action', '=', 'platform.workspace.status_changed')
            ->where('subject_id', '=', $this->otherWorkspaceId)
            ->count();

        $res = (new PlatformController())->suspendWorkspace(
            $this->request([], ['id' => (string) $this->otherWorkspaceId])
        );
        $this->assertSame(302, $res->getStatus());

        $statusId = (int) app('db')->table('workspaces')
            ->where('id', '=', $this->otherWorkspaceId)
            ->value('workspace_status_id');
        $this->assertSame(status_id('workspace_statuses', 'suspended'), $statusId);

        $after = app('db')->table('activity_logs')
            ->where('action', '=', 'platform.workspace.status_changed')
            ->where('subject_id', '=', $this->otherWorkspaceId)
            ->count();
        $this->assertTrue($after > $before, 'Suspending a workspace must write an audit row.');

        // And it can be re-activated.
        $res = (new PlatformController())->activateWorkspace(
            $this->request([], ['id' => (string) $this->otherWorkspaceId])
        );
        $this->assertSame(302, $res->getStatus());
        $this->assertSame(
            status_id('workspace_statuses', 'active'),
            (int) app('db')->table('workspaces')->where('id', '=', $this->otherWorkspaceId)->value('workspace_status_id')
        );
    }

    public function test_users_list_is_cross_tenant(): void
    {
        $res = (new PlatformController())->users($this->request());
        $this->assertSame(200, $res->getStatus());

        $content = $res->getContent();
        // The foreign owner (a member of a different workspace) is visible here.
        $this->assertTrue(str_contains($content, 'Foreign Owner'));
        $this->assertTrue(str_contains($content, $this->otherOwnerEmail));
        $this->assertTrue(str_contains($content, 'Platform Admin'));
    }

    public function test_suspend_and_activate_user_works(): void
    {
        // Act on the foreign owner, never the acting admin (Wave-1 gotcha: don't
        // suspend yourself).
        $res = (new PlatformController())->suspendUser(
            $this->request([], ['id' => (string) $this->otherOwnerId])
        );
        $this->assertSame(302, $res->getStatus());
        $this->assertSame(
            lookup_id('user_status', 'suspended'),
            (int) app('db')->table('users')->where('id', '=', $this->otherOwnerId)->value('user_status_id')
        );

        $audit = app('db')->table('activity_logs')
            ->where('action', '=', 'platform.user.status_changed')
            ->where('subject_id', '=', $this->otherOwnerId)
            ->count();
        $this->assertTrue($audit > 0, 'Suspending a user must write an audit row.');

        $res = (new PlatformController())->activateUser(
            $this->request([], ['id' => (string) $this->otherOwnerId])
        );
        $this->assertSame(302, $res->getStatus());
        $this->assertSame(
            lookup_id('user_status', 'active'),
            (int) app('db')->table('users')->where('id', '=', $this->otherOwnerId)->value('user_status_id')
        );
    }

    public function test_impersonation_switches_identity_and_restores(): void
    {
        $sessionKey = (string) config('auth.session_key', 'auth_user_id');

        // Start impersonating the foreign owner.
        $res = (new PlatformController())->impersonate(
            $this->request([], ['user_id' => (string) $this->otherOwnerId])
        );
        $this->assertSame(302, $res->getStatus());

        // The active session identity is now the impersonated user, and the
        // impersonator's id is parked for restoration.
        $this->assertSame($this->otherOwnerId, (int) session()->get($sessionKey));
        $this->assertSame($this->adminId, (int) session()->get('platform_impersonator_id'));

        $started = app('db')->table('activity_logs')
            ->where('action', '=', 'platform.impersonation.started')
            ->where('subject_id', '=', $this->otherOwnerId)
            ->count();
        $this->assertTrue($started > 0, 'Starting impersonation must be audited.');

        // Stop impersonating — restores the original super admin.
        $res = (new PlatformController())->stopImpersonating($this->request([], ['_' => '1']));
        $this->assertSame(302, $res->getStatus());
        $this->assertSame($this->adminId, (int) session()->get($sessionKey));
        $this->assertFalse(session()->has('platform_impersonator_id'));

        $stopped = app('db')->table('activity_logs')
            ->where('action', '=', 'platform.impersonation.stopped')
            ->count();
        $this->assertTrue($stopped > 0, 'Stopping impersonation must be audited.');
    }

    public function test_cannot_impersonate_self(): void
    {
        $res = (new PlatformController())->impersonate(
            $this->request([], ['user_id' => (string) $this->adminId])
        );
        // Redirects back with an error; identity is unchanged, nothing is parked.
        $this->assertSame(302, $res->getStatus());
        $this->assertFalse(session()->has('platform_impersonator_id'));
    }

    /**
     * Reset the auth resolution + AccessControl per-request cache on the EXISTING
     * singletons (preserving registered policy gates). Re-resolves auth() from the
     * current session id and reflects the active user's permissions immediately.
     * Used both up-front in setUp (independence) and after an identity switch.
     */
    private function resetAuthState(): void
    {
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
};
