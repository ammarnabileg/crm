<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\DTOs\RegisterUserData;
use App\Services\Auth\RegistrationService;
use Tests\TestCase;

/**
 * RBAC ↔ route ↔ catalogue integrity (regression for the 403 the browser QA found:
 * `recruitment.*` was gated on routes and present in config, but the live
 * `permissions` catalogue had drifted behind config — so no role was granted it and
 * the whole ATS UI 403'd for everyone but the super-admin). These lock the contract:
 *   1. every permission a route enforces is defined in config,
 *   2. every permission in config exists in the live catalogue (no sync drift),
 *   3. a provisioned Owner ('*') actually receives recruitment permissions.
 */
return new class extends TestCase {
    protected bool $useDatabaseTransaction = true;

    /** @return string[] */
    private function routePermissions(): array
    {
        $src = (string) file_get_contents(base_path('routes/web.php'));
        preg_match_all('/permission:([a-z0-9_.]+)/i', $src, $m);

        return array_values(array_unique($m[1] ?? []));
    }

    /** @return string[] */
    private function configPermissionKeys(): array
    {
        return array_map(static fn (array $p): string => $p[0], (array) config('rbac.permissions', []));
    }

    public function test_every_route_permission_is_defined_in_config(): void
    {
        $config = $this->configPermissionKeys();
        foreach ($this->routePermissions() as $perm) {
            $this->assertTrue(in_array($perm, $config, true), "route permission '{$perm}' is not defined in config/rbac.php");
        }
    }

    public function test_every_config_permission_exists_in_the_live_catalogue(): void
    {
        $dbKeys = array_map(static fn ($r) => $r['key'], app('db')->table('permissions')->select('key')->get());
        foreach ($this->configPermissionKeys() as $key) {
            $this->assertTrue(in_array($key, $dbKeys, true), "config permission '{$key}' is missing from the permissions table (sync drift)");
        }
    }

    public function test_recruitment_permissions_are_registered(): void
    {
        // The exact permissions the ATS routes enforce must be in the catalogue.
        $dbKeys = array_map(static fn ($r) => $r['key'], app('db')->table('permissions')->select('key')->get());
        $this->assertTrue(in_array('recruitment.view', $dbKeys, true));
        $this->assertTrue(in_array('recruitment.manage', $dbKeys, true));
    }

    public function test_provisioned_owner_receives_recruitment_permissions(): void
    {
        $result = app(RegistrationService::class)->register(RegisterUserData::fromArray([
            'name' => 'Perm Owner', 'email' => 'perm.owner@example.com',
            'password' => 'StrongPass!234', 'workspace_name' => 'Perm Co',
        ]));
        $workspaceId = (int) $result['workspace']->getKey();

        $ownerRoleId = (int) app('db')->table('roles')
            ->where('slug', '=', 'owner')->where('workspace_id', '=', $workspaceId)->value('id');
        $this->assertTrue($ownerRoleId > 0);

        $granted = app('db')->table('role_permissions')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('role_permissions.role_id', '=', $ownerRoleId)
            ->where('permissions.key', '=', 'recruitment.view')
            ->count();
        $this->assertTrue($granted > 0, 'Owner role was not granted recruitment.view');
    }
};
