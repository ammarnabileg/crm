<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Controllers\App\MemberController;
use App\Core\Request;
use App\Models\Membership;
use App\Models\User;
use App\Services\Tenancy\WorkspaceService;
use Tests\TestCase;

/**
 * Members web layer (docs/47 RBAC) — the members list renders end to end
 * (controller -> service -> view -> layout) with real data, an invite creates a
 * user + membership, and a role update writes membership_roles. Reads are gated by
 * members.view and writes by the matching members.* permission, enforced inside
 * the controller via abort_unless(can(...)).
 *
 * Fixtures are built fresh inside a rolled-back transaction (mirrors PolicyTest)
 * so the suite never depends on or mutates seeded data: a brand-new workspace
 * gives its creator the Owner role (every tenant permission) and the default
 * tenant role set, and the creator is authenticated via the session so the RBAC
 * gates resolve for real.
 */
return new class extends TestCase {
    protected bool $useDatabaseTransaction = true;

    private int $workspaceId = 0;
    private int $ownerId = 0;
    private string $ownerEmail = '';

    public function setUp(): void
    {
        $this->ownerEmail = 'owner-' . uniqid() . '@members.test';
        $owner = User::create([
            'name'           => 'Workspace Owner',
            'email'          => $this->ownerEmail,
            'password'       => 'x',
            'user_status_id' => lookup_id('user_status', 'active'),
        ]);
        $this->ownerId = (int) $owner->getKey();

        $workspace = (new WorkspaceService())->create($owner, 'Members Test Co');
        $this->workspaceId = (int) $workspace->getKey();
        tenant()->setById($this->workspaceId);

        // Authenticate the owner so the controller's can() gates resolve.
        session()->put((string) config('auth.session_key', 'auth_user_id'), $this->ownerId);
    }

    /**
     * Rendering touches auth() (the AuthManager resolves the user once and caches
     * "resolved"). Reset that resolution + the AccessControl per-request cache on
     * the EXISTING singletons (preserving registered policy gates) so later
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

    public function test_members_page_renders_with_existing_member(): void
    {
        $res = (new MemberController())->index($this->request());
        $this->assertSame(200, $res->getStatus());
        $content = $res->getContent();
        $this->assertTrue(str_contains($content, 'Members'));
        // The owner (an existing member) is listed by name and email.
        $this->assertTrue(str_contains($content, 'Workspace Owner'));
        $this->assertTrue(str_contains($content, $this->ownerEmail));
    }

    public function test_invite_creates_user_and_membership(): void
    {
        $email = 'invitee-' . uniqid() . '@members.test';

        $res = (new MemberController())->invite($this->request([], [
            'name'  => 'New Invitee',
            'email' => $email,
            'title' => 'Recruiter',
        ]));
        // Successful invite redirects back to the members list.
        $this->assertSame(302, $res->getStatus());

        $userId = app('db')->table('users')->where('email', '=', $email)->value('id');
        $this->assertNotNull($userId);

        $membership = Membership::withoutTenantScope()
            ->where('workspace_id', '=', $this->workspaceId)
            ->where('user_id', '=', (int) $userId)
            ->first();
        $this->assertNotNull($membership);
        $this->assertSame(
            lookup_id('membership_status', 'active'),
            (int) $membership['membership_status_id']
        );
    }

    public function test_update_roles_writes_membership_roles(): void
    {
        // Pick the seeded "member" tenant role for this workspace.
        $roleId = (int) app('db')->table('roles')
            ->where('workspace_id', '=', $this->workspaceId)
            ->where('slug', '=', 'member')
            ->value('id');
        $this->assertTrue($roleId > 0);

        // Act on a SECOND member, not the signed-in owner: managing another member's
        // roles is the real use case, and it keeps the actor's own permissions intact
        // (clearing the owner's roles would strip the members.update grant that gates
        // this very action).
        $email = 'role-target-' . uniqid() . '@members.test';
        (new MemberController())->invite($this->request([], ['name' => 'Role Target', 'email' => $email]));
        $targetUserId = (int) app('db')->table('users')->where('email', '=', $email)->value('id');
        $targetMembershipId = (int) Membership::withoutTenantScope()
            ->where('workspace_id', '=', $this->workspaceId)
            ->where('user_id', '=', $targetUserId)
            ->value('id');
        $this->assertTrue($targetMembershipId > 0);

        $res = (new MemberController())->updateRoles($this->request([], [
            'membership_id' => (string) $targetMembershipId,
            'roles'         => [(string) $roleId],
        ]));
        $this->assertSame(302, $res->getStatus());

        $exists = app('db')->table('membership_roles')
            ->where('membership_id', '=', $targetMembershipId)
            ->where('role_id', '=', $roleId)
            ->exists();
        $this->assertTrue($exists);
    }
};
