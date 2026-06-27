<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Request;
use App\Core\Response;
use App\Http\Middleware\RequireSuperAdmin;
use App\Models\User;
use App\Services\Rbac\RbacManager;
use App\Services\Tenancy\WorkspaceService;
use Tests\TestCase;

/**
 * RequireSuperAdmin guard — cross-tenant privilege-escalation regression.
 *
 * The platform operations area (`/system/*` — the .env editor, cross-tenant DB
 * backups, the platform console) was gated on `permission:system.manage`. But the
 * workspace Owner role carries `permissions => '*'`, so EVERY customer Owner is
 * granted `system.manage` too — meaning every customer could reach the platform
 * ops area. That is a cross-tenant privilege escalation.
 *
 * The fix gates `/system/*` on the `super_admin` middleware instead. Super-admin
 * is a GLOBAL role (`roles.slug = super-admin`, `workspace_id IS NULL`) that the
 * `*` wildcard cannot grant, so it is the correct boundary. These tests prove:
 *   (a) an Owner who DOES hold system.manage is still refused (the hole is shut),
 *   (b) a genuine super admin passes through,
 *   (c) an unauthenticated request is refused,
 *   (d) an API caller gets a 403 JSON body rather than a redirect.
 */
return new class extends TestCase {
    protected bool $useDatabaseTransaction = true;

    private int $ownerId = 0;
    private int $ownerWorkspaceId = 0;
    private int $superId = 0;

    public function setUp(): void
    {
        $this->resetAuthState();

        // A plain workspace Owner. WorkspaceService::create provisions the Owner
        // role whose '*' wildcard grants every permission — including system.manage.
        $owner = User::create([
            'name'           => 'Tenant Owner',
            'email'          => 'owner-' . uniqid() . '@guard.test',
            'password'       => 'x',
            'user_status_id' => lookup_id('user_status', 'active'),
        ]);
        $this->ownerId = (int) $owner->getKey();
        $this->ownerWorkspaceId = (int) (new WorkspaceService())->create($owner, 'Owner Co')->getKey();

        // A genuine platform super admin (global role, workspace_id IS NULL).
        $super = User::create([
            'name'           => 'Platform Admin',
            'email'          => 'super-' . uniqid() . '@guard.test',
            'password'       => 'x',
            'user_status_id' => lookup_id('user_status', 'active'),
        ]);
        $this->superId = (int) $super->getKey();
        $superWorkspaceId = (int) (new WorkspaceService())->create($super, 'Super Home')->getKey();
        $rbac = new RbacManager(app('db'));
        $rbac->assignGlobalRole($this->superId, $rbac->ensureSuperAdminRole());
        // Give the super admin an active tenant too, to prove the guard never needs
        // tenant context — super-admin status alone is sufficient.
        $this->superWorkspaceId = $superWorkspaceId;
    }

    private int $superWorkspaceId = 0;

    public function tearDown(): void
    {
        session()->forget((string) config('auth.session_key', 'auth_user_id'));
        tenant()->clear();
        $this->resetAuthState();
    }

    private function request(bool $wantsJson = false): Request
    {
        $server = ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/system/environment'];
        if ($wantsJson) {
            $server['HTTP_ACCEPT'] = 'application/json';
        }
        $req = new Request([], [], $server, [], []);
        app()->instance('request', $req);

        return $req;
    }

    private function loginAs(int $id): void
    {
        session()->put((string) config('auth.session_key', 'auth_user_id'), $id);
        $this->resetAuthState();
        session()->put((string) config('auth.session_key', 'auth_user_id'), $id);
    }

    public function test_owner_with_system_manage_is_still_refused(): void
    {
        $this->loginAs($this->ownerId);
        // Owner permissions are workspace-scoped (membership roles), so the active
        // tenant must be set for can() to resolve them — this is the very state a
        // signed-in Owner is in. The guard itself is tenant-independent.
        tenant()->setById($this->ownerWorkspaceId);

        // The hole made concrete: the Owner DOES hold system.manage (via '*') …
        $this->assertTrue(can('system.manage'), 'Owner is expected to hold system.manage via the "*" wildcard.');
        // … but is NOT a super admin, so the guard must still refuse them.
        $this->assertFalse(auth()->user()->isSuperAdmin());

        $reached = false;
        $res = (new RequireSuperAdmin())->handle($this->request(), static function () use (&$reached): Response {
            $reached = true;

            return Response::make('platform-area', 200);
        });

        $this->assertFalse($reached, 'A non-super-admin Owner must never reach a /system/* route.');
        $this->assertSame(302, $res->getStatus());
        $this->assertTrue(str_contains((string) $res->getHeader('Location'), 'dashboard'));
    }

    public function test_super_admin_passes_through(): void
    {
        $this->loginAs($this->superId);
        tenant()->setById($this->superWorkspaceId);

        $this->assertTrue(auth()->user()->isSuperAdmin());

        $reached = false;
        $res = (new RequireSuperAdmin())->handle($this->request(), static function () use (&$reached): Response {
            $reached = true;

            return Response::make('platform-area', 200);
        });

        $this->assertTrue($reached, 'A genuine super admin must reach the platform area.');
        $this->assertSame(200, $res->getStatus());
        $this->assertSame('platform-area', $res->getContent());
    }

    public function test_unauthenticated_request_is_refused(): void
    {
        session()->forget((string) config('auth.session_key', 'auth_user_id'));
        $this->resetAuthState();

        $reached = false;
        $res = (new RequireSuperAdmin())->handle($this->request(), static function () use (&$reached): Response {
            $reached = true;

            return Response::make('platform-area', 200);
        });

        $this->assertFalse($reached);
        $this->assertSame(302, $res->getStatus());
    }

    public function test_api_caller_gets_403_json(): void
    {
        $this->loginAs($this->ownerId);

        $res = (new RequireSuperAdmin())->handle($this->request(true), static fn (): Response => Response::make('x', 200));

        $this->assertSame(403, $res->getStatus());
        $this->assertTrue(str_contains((string) $res->getContent(), 'Super-admin'));
    }

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
