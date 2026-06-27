<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Rbac\RbacManager;
use Tests\TestCase;

/**
 * RBAC permission sync is resilient to migration ordering (install-blocker regression).
 *
 * The re-sync migrations (0046/0047/0130/0131…) call RbacManager::syncPermissions(),
 * which reads the FULL live config catalogue. On a fresh install a permission can be
 * seen before the migration that creates its module has run (e.g. `automation.*`
 * before the `automation` module in 0131). syncPermissions() must SKIP such a
 * permission this pass — never throw — or the installer dies mid-migration with
 * "permission '…' references unknown module '…'". A later sync (once the module
 * exists) creates it. This test pins that tolerant behaviour.
 */
return new class extends TestCase {
    protected bool $useDatabaseTransaction = true;

    /** @var array<int,array{0:string,1:string,2:string,3:string}> */
    private array $originalPermissions = [];

    public function setUp(): void
    {
        $this->originalPermissions = (array) config('rbac.permissions', []);
    }

    public function tearDown(): void
    {
        // Restore the real catalogue — config is an in-memory singleton the DB
        // transaction rollback does not touch.
        app('config')->set('rbac.permissions', $this->originalPermissions);
    }

    public function test_sync_skips_a_permission_whose_module_does_not_exist(): void
    {
        // A catalogue with one valid permission and one that points at a module which
        // does not exist yet (the fresh-install ordering case).
        app('config')->set('rbac.permissions', [
            ['dashboard.view', 'View dashboard', 'dashboard', 'Access the main dashboard.'],
            ['ghost.view', 'Ghost', 'module_that_does_not_exist_yet', 'Created by a later migration.'],
        ]);

        $threw = false;
        try {
            (new RbacManager(app('db')))->syncPermissions();
        } catch (\Throwable $e) {
            $threw = true;
        }

        $this->assertFalse($threw, 'syncPermissions must not throw on a permission for a not-yet-created module.');
        // The valid permission is synced…
        $this->assertTrue(app('db')->table('permissions')->where('key', '=', 'dashboard.view')->exists());
        // …the one with a missing module is skipped this pass (a later sync creates it).
        $this->assertFalse(app('db')->table('permissions')->where('key', '=', 'ghost.view')->exists());
    }
};
