<?php

declare(strict_types=1);

namespace Tests\Security;

use App\DTOs\RegisterUserData;
use App\Models\Job;
use App\Services\Ats\JobManager;
use App\Services\Auth\RegistrationService;
use Tests\TestCase;

/**
 * Multi-tenant isolation (Testing & QA Bible) — with two workspaces, one tenant can
 * never see, list or reach another tenant's data. The model layer is fail-closed:
 * every query is scoped by workspace_id, so cross-tenant reads return nothing.
 */
return new class extends TestCase {
    protected bool $useDatabaseTransaction = true;

    public function test_one_workspace_cannot_see_anothers_data(): void
    {
        $reg = app(RegistrationService::class);
        $a = $reg->register(RegisterUserData::fromArray([
            'name' => 'Alpha Owner', 'email' => 'alpha.iso@example.com',
            'password' => 'StrongPass!234', 'workspace_name' => 'Alpha Inc',
        ]));
        $b = $reg->register(RegisterUserData::fromArray([
            'name' => 'Beta Owner', 'email' => 'beta.iso@example.com',
            'password' => 'StrongPass!234', 'workspace_name' => 'Beta Inc',
        ]));

        // Alpha creates a job.
        tenant()->setTenant($a['workspace']);
        $jobA = (new JobManager())->create(['title' => 'Alpha Secret Job'], (int) $a['user']->getKey());
        $idA = (int) $jobA->getKey();
        $this->assertNotNull(Job::find($idA));                 // Alpha sees its own job
        $alphaTitles = array_map(static fn ($j) => $j->title, Job::all());
        $this->assertTrue(in_array('Alpha Secret Job', $alphaTitles, true));

        // Switch to Beta — Alpha's job must be invisible and unreachable.
        tenant()->setTenant($b['workspace']);
        $this->assertNull(Job::find($idA));                    // fail-closed read across tenants
        $betaTitles = array_map(static fn ($j) => $j->title, Job::all());
        $this->assertFalse(in_array('Alpha Secret Job', $betaTitles, true));
        $this->assertSame([], Job::where('title', '=', 'Alpha Secret Job')); // scoped query returns nothing

        // Beta's own job is separate and does not leak back to Alpha.
        $jobB = (new JobManager())->create(['title' => 'Beta Secret Job'], (int) $b['user']->getKey());
        $idB = (int) $jobB->getKey();
        tenant()->setTenant($a['workspace']);
        $this->assertNull(Job::find($idB));
    }
};
