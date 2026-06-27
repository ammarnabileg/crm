<?php

declare(strict_types=1);

use App\Core\Database;
use App\Services\Rbac\RbacManager;
use Database\Migration;

/**
 * Re-sync the RBAC catalogue after `workspace.delete` was added to config/rbac.php.
 *
 * `workspace.delete` is the permission behind `WorkspacePolicy::delete()` (owner-only);
 * it was registered as a policy gate but missing from the permission catalogue, so the
 * Final Enterprise Audit flagged it as an undefined-but-referenced permission. Adding it
 * to config + this idempotent re-sync upserts it into the live `permissions` table and
 * re-applies each system role's set (owner `*` gains it; admin/member do not). Fresh
 * installs already pick it up via DatabaseSeeder; re-running here is harmless.
 */
return new class extends Migration {
    public function up(Database $db): void
    {
        (new RbacManager($db))->resyncSystemRolePermissions();
    }

    public function down(Database $db): void
    {
        // No-op: permission grants are config-derived and idempotently re-synced
        // forward; rolling back must not strip access from live workspaces.
    }
};
