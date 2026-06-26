<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Repositories\CompanyRepositoryInterface;
use App\Contracts\Repositories\UserRepositoryInterface;
use App\Models\AiCredential;
use App\Models\User;
use App\Repositories\AiCredentialRepository;
use App\Services\Tenancy\CompanyService;
use Tests\TestCase;

/**
 * Repository pattern, UUID generation, and soft deletes against a real schema.
 * Runs in a rolled-back transaction (docs/47 EAS-3/EAS-8).
 */
return new class extends TestCase {
    protected bool $useDatabaseTransaction = true;

    public function setUp(): void
    {
        $companyId = (int) app('db')->table('companies')->orderBy('id')->value('id');
        tenant()->setById($companyId);
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

    public function test_company_repository_find_by_slug_and_for_user(): void
    {
        $repo = app(CompanyRepositoryInterface::class);
        $company = app('db')->table('companies')->orderBy('id')->first();

        $found = $repo->findBySlug((string) $company['slug']);
        $this->assertNotNull($found);
        $this->assertSame((int) $company['id'], (int) $found->id);

        $companies = $repo->forUser((int) $company['owner_id']);
        $this->assertTrue(count($companies) >= 1);
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

    public function test_company_service_provisions_uuids_on_raw_inserts(): void
    {
        $owner = User::create([
            'name' => 'UUID Owner', 'email' => 'uuid-' . uniqid() . '@test.local',
            'password' => 'x', 'status' => 'active',
        ]);
        $company = (new CompanyService())->create($owner, 'UUID Co');

        // Company, membership, role and subscription are created via raw inserts
        // in the service — all must still carry a generated uuid (docs/47 EAS-8).
        $this->assertSame(36, strlen((string) $company->uuid));

        $membership = app('db')->table('memberships')->where('company_id', '=', $company->getKey())->first();
        $this->assertNotNull($membership['uuid']);

        $role = app('db')->table('roles')->where('company_id', '=', $company->getKey())->first();
        $this->assertNotNull($role['uuid']);

        $subscription = app('db')->table('subscriptions')->where('company_id', '=', $company->getKey())->first();
        $this->assertNotNull($subscription['uuid']);
    }

    public function test_repository_is_tenant_scoped(): void
    {
        $repo = new AiCredentialRepository();
        $companies = app('db')->table('companies')->orderBy('id')->limit(2)->get();
        if (count($companies) < 2) {
            return; // need two tenants for this assertion
        }

        // Create under company A.
        tenant()->setById((int) $companies[0]['id']);
        $cred = $repo->create([
            'provider'    => 'deepseek',
            'credentials' => AiCredential::encryptSecrets(['api_key' => 'd']),
            'is_active'   => 1,
        ]);
        $id = (int) $cred->id;
        $this->assertNotNull($repo->find($id));

        // Switch to company B — the row must be invisible.
        tenant()->setById((int) $companies[1]['id']);
        $this->assertNull($repo->find($id));
    }
};
