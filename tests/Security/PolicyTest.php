<?php

declare(strict_types=1);

namespace Tests\Security;

use App\Domain\Policies\CompanyPolicy;
use App\Models\Company;
use App\Models\User;
use App\Services\Tenancy\CompanyService;
use Tests\TestCase;

/**
 * CompanyPolicy enforces ownership + tenant membership beyond raw permissions
 * (docs/47 EAS-11). Uses fresh fixtures inside a rolled-back transaction so it
 * never depends on or mutates seeded data.
 */
return new class extends TestCase {
    protected bool $useDatabaseTransaction = true;

    private function policy(): CompanyPolicy
    {
        return new CompanyPolicy(app('access'), app('tenant'));
    }

    public function test_owner_can_update_and_delete_but_stranger_cannot(): void
    {
        $owner = User::create([
            'name' => 'Owner P', 'email' => 'owner-' . uniqid() . '@test.local',
            'password' => 'x', 'status' => 'active',
        ]);
        $stranger = User::create([
            'name' => 'Stranger P', 'email' => 'stranger-' . uniqid() . '@test.local',
            'password' => 'x', 'status' => 'active',
        ]);

        $company = (new CompanyService())->create($owner, 'Policy Test Co');
        tenant()->setById((int) $company->getKey());

        $policy = $this->policy();

        // Owner.
        $this->assertTrue($policy->view($owner, $company));
        $this->assertTrue($policy->update($owner, $company));
        $this->assertTrue($policy->delete($owner, $company));

        // Stranger (no membership, not owner).
        $this->assertFalse($policy->view($stranger, $company));
        $this->assertFalse($policy->update($stranger, $company));
        $this->assertFalse($policy->delete($stranger, $company));
    }

    public function test_access_uses_policy_only_with_object_context(): void
    {
        // Build an owner + company, make the company the active tenant.
        $owner = User::create([
            'name' => 'Owner Q', 'email' => 'ownerq-' . uniqid() . '@test.local',
            'password' => 'x', 'status' => 'active',
        ]);
        $company = (new CompanyService())->create($owner, 'Gate Test Co');
        tenant()->setById((int) $company->getKey());

        // Authenticate the owner for AccessControl.
        session()->put((string) config('auth.session_key', 'auth_user_id'), (int) $owner->getKey());

        // With an object context, the registered policy decides → allowed.
        $this->assertTrue(access()->allows('company.delete', $company));

        // Without a context, it falls back to the permission lookup. The owner's
        // role does include company.update, so allows() is true for that key.
        $this->assertTrue(access()->allows('company.update'));

        session()->forget((string) config('auth.session_key', 'auth_user_id'));
    }
};
