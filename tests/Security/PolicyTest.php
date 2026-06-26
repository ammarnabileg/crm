<?php

declare(strict_types=1);

namespace Tests\Security;

use App\Domain\Policies\WorkspacePolicy;
use App\Models\Workspace;
use App\Models\User;
use App\Services\Tenancy\WorkspaceService;
use Tests\TestCase;

/**
 * WorkspacePolicy enforces ownership + tenant membership beyond raw permissions
 * (docs/47 EAS-11). Uses fresh fixtures inside a rolled-back transaction so it
 * never depends on or mutates seeded data.
 */
return new class extends TestCase {
    protected bool $useDatabaseTransaction = true;

    private function policy(): WorkspacePolicy
    {
        return new WorkspacePolicy(app('access'), app('tenant'));
    }

    public function test_owner_can_update_and_delete_but_stranger_cannot(): void
    {
        $owner = User::create([
            'name' => 'Owner P', 'email' => 'owner-' . uniqid() . '@test.local',
            'password' => 'x', 'user_status_id' => lookup_id('user_status', 'active'),
        ]);
        $stranger = User::create([
            'name' => 'Stranger P', 'email' => 'stranger-' . uniqid() . '@test.local',
            'password' => 'x', 'user_status_id' => lookup_id('user_status', 'active'),
        ]);

        $workspace = (new WorkspaceService())->create($owner, 'Policy Test Co');
        tenant()->setById((int) $workspace->getKey());

        $policy = $this->policy();

        // Owner.
        $this->assertTrue($policy->view($owner, $workspace));
        $this->assertTrue($policy->update($owner, $workspace));
        $this->assertTrue($policy->delete($owner, $workspace));

        // Stranger (no membership, not owner).
        $this->assertFalse($policy->view($stranger, $workspace));
        $this->assertFalse($policy->update($stranger, $workspace));
        $this->assertFalse($policy->delete($stranger, $workspace));
    }

    public function test_access_uses_policy_only_with_object_context(): void
    {
        // Build an owner + workspace, make the workspace the active tenant.
        $owner = User::create([
            'name' => 'Owner Q', 'email' => 'ownerq-' . uniqid() . '@test.local',
            'password' => 'x', 'user_status_id' => lookup_id('user_status', 'active'),
        ]);
        $workspace = (new WorkspaceService())->create($owner, 'Gate Test Co');
        tenant()->setById((int) $workspace->getKey());

        // Authenticate the owner for AccessControl.
        session()->put((string) config('auth.session_key', 'auth_user_id'), (int) $owner->getKey());

        // With an object context, the registered policy decides → allowed.
        $this->assertTrue(access()->allows('workspace.delete', $workspace));

        // Without a context, it falls back to the permission lookup. The owner's
        // role does include workspace.update, so allows() is true for that key.
        $this->assertTrue(access()->allows('workspace.update'));

        session()->forget((string) config('auth.session_key', 'auth_user_id'));
    }
};
