<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Repositories\WorkspaceRepositoryInterface;
use App\Contracts\Repositories\UserRepositoryInterface;
use App\Models\AiCredential;
use App\Models\User;
use App\Repositories\AiCredentialRepository;
use App\Services\Tenancy\WorkspaceService;
use Tests\TestCase;

/**
 * Repository pattern, UUID generation, and soft deletes against a real schema.
 * Runs in a rolled-back transaction (docs/47 EAS-3/EAS-8).
 */
return new class extends TestCase {
    protected bool $useDatabaseTransaction = true;

    public function setUp(): void
    {
        $workspaceId = (int) app('db')->table('workspaces')->orderBy('id')->value('id');
        tenant()->setById($workspaceId);
    }

    public function test_user_repository_resolves_via_interface(): void
    {
        $repo = app(UserRepositoryInterface::class);
        $this->assertInstanceOf(\App\Repositories\UserRepository::class, $repo);
    }

    public function test_user_repository_find_by_email(): void
    {
        $repo = app(UserRepositoryInterface::class);
        $email = (string) app('db')->table('users')->orderBy('id')->value('email');

        $user = $repo->findByEmail(strtoupper($email)); // case-insensitive
        $this->assertNotNull($user);
        $this->assertSame($email, $user->email);
        $this->assertTrue($repo->emailExists($email));
        $this->assertFalse($repo->emailExists('definitely-not-here@example.com'));
    }

    public function test_workspace_repository_find_by_slug_and_for_user(): void
    {
        $repo = app(WorkspaceRepositoryInterface::class);
        $workspace = app('db')->table('workspaces')->orderBy('id')->first();

        $found = $repo->findBySlug((string) $workspace['slug']);
        $this->assertNotNull($found);
        $this->assertSame((int) $workspace['id'], (int) $found->id);

        $workspaces = $repo->forUser((int) $workspace['owner_id']);
        $this->assertTrue(count($workspaces) >= 1);
    }

    public function test_create_generates_uuid(): void
    {
        $repo = new AiCredentialRepository();
        $cred = $repo->create([
            'provider'    => 'openai',
            'credentials' => AiCredential::encryptSecrets(['api_key' => 'sk-test-123']),
            'is_active'   => 1,
        ]);

        $uuid = (string) $cred->uuid;
        $this->assertSame(36, strlen($uuid));
        $this->assertSame(4, substr_count($uuid, '-'));
        // version 4 marker
        $this->assertSame('4', $uuid[14]);
    }

    public function test_soft_delete_hides_then_restore_brings_back(): void
    {
        $repo = new AiCredentialRepository();
        $cred = $repo->create([
            'provider'    => 'anthropic',
            'credentials' => AiCredential::encryptSecrets(['api_key' => 'sk-ant']),
            'is_active'   => 1,
        ]);
        $id = (int) $cred->id;

        $this->assertNotNull($repo->find($id));

        // Soft delete: default query no longer finds it.
        $this->assertTrue($repo->delete($id));
        $this->assertNull($repo->find($id));

        // withTrashed still sees it and it is marked trashed.
        $trashedRow = AiCredential::withTrashed()->where('id', '=', $id)->first();
        $this->assertNotNull($trashedRow);
        $this->assertNotNull($trashedRow['deleted_at']);

        // Restore brings it back into default scope.
        $this->assertTrue($repo->restore($id));
        $this->assertNotNull($repo->find($id));
    }

    public function test_force_delete_removes_permanently(): void
    {
        $repo = new AiCredentialRepository();
        $cred = $repo->create([
            'provider'    => 'gemini',
            'credentials' => AiCredential::encryptSecrets(['api_key' => 'g']),
            'is_active'   => 1,
        ]);
        $id = (int) $cred->id;

        $this->assertTrue($repo->forceDelete($id));
        $this->assertNull(AiCredential::withTrashed()->where('id', '=', $id)->first());
    }

    public function test_workspace_service_provisions_uuids_on_raw_inserts(): void
    {
        $owner = User::create([
            'name' => 'UUID Owner', 'email' => 'uuid-' . uniqid() . '@test.local',
            'password' => 'x', 'user_status_id' => lookup_id('user_status', 'active'),
        ]);
        $workspace = (new WorkspaceService())->create($owner, 'UUID Co');

        // Workspace, membership, role and subscription are created via raw inserts
        // in the service — all must still carry a generated uuid (docs/47 EAS-8).
        $this->assertSame(36, strlen((string) $workspace->uuid));

        $membership = app('db')->table('memberships')->where('workspace_id', '=', $workspace->getKey())->first();
        $this->assertNotNull($membership['uuid']);

        $role = app('db')->table('roles')->where('workspace_id', '=', $workspace->getKey())->first();
        $this->assertNotNull($role['uuid']);

        $subscription = app('db')->table('subscriptions')->where('workspace_id', '=', $workspace->getKey())->first();
        $this->assertNotNull($subscription['uuid']);
    }

    public function test_repository_is_tenant_scoped(): void
    {
        $repo = new AiCredentialRepository();
        $workspaces = app('db')->table('workspaces')->orderBy('id')->limit(2)->get();
        if (count($workspaces) < 2) {
            return; // need two tenants for this assertion
        }

        // Create under workspace A.
        tenant()->setById((int) $workspaces[0]['id']);
        $cred = $repo->create([
            'provider'    => 'deepseek',
            'credentials' => AiCredential::encryptSecrets(['api_key' => 'd']),
            'is_active'   => 1,
        ]);
        $id = (int) $cred->id;
        $this->assertNotNull($repo->find($id));

        // Switch to workspace B — the row must be invisible.
        tenant()->setById((int) $workspaces[1]['id']);
        $this->assertNull($repo->find($id));
    }
};
