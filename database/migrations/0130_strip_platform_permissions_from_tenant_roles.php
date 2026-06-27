<?php

declare(strict_types=1);

use App\Core\Database;
use App\Services\Rbac\RbacManager;
use Database\Migration;

/**
 * Security: strip platform-scope permissions (e.g. `system.manage`) from every
 * existing TENANT role.
 *
 * Until now the Owner role's `permissions => '*'` wildcard expanded to EVERY
 * catalogued permission — including `system.manage`, the platform-owner power
 * behind the `.env` editor, cross-tenant backup/restore and the platform
 * console. So every customer Owner held `system.manage` (a cross-tenant
 * privilege escalation). The route layer is now gated by the `super_admin`
 * middleware, and `RbacManager::tenantGrantablePermissionIds()` now excludes the
 * `system` module (config `rbac.platform_modules`) from the tenant `*` expansion.
 *
 * This migration applies that fix to ALREADY-PROVISIONED workspaces: the
 * idempotent `resyncSystemRolePermissions()` re-applies each existing workspace
 * role's configured set with the corrected expansion, so Owners lose
 * `system.manage` while keeping every tenant permission. The global super-admin
 * role (workspace_id IS NULL) is untouched and still holds it (separate path).
 * Fresh installs already provision correctly; re-running here is harmless.
 */
return new class extends Migration {
    public function up(Database $db): void
    {
        (new RbacManager($db))->resyncSystemRolePermissions();
    }

    public function down(Database $db): void
    {
        // No-op: this is a security tightening. Rolling back must never re-grant a
        // platform permission to tenant roles. The forward re-sync is idempotent.
    }
};
