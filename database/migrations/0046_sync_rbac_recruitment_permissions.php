<?php

declare(strict_types=1);

use App\Core\Database;
use App\Services\Rbac\RbacManager;
use Database\Migration;

/**
 * Sync the RBAC permission catalogue + re-grant system roles (idempotent).
 *
 * The ATS phase added `recruitment.view` / `recruitment.manage` to config/rbac.php,
 * but an already-installed system never re-ran the permission sync — so the live
 * `permissions` catalogue (and every role's grants) drifted behind config. Routes
 * gated by `recruitment.*` therefore returned 403 for everyone except the
 * super-admin (who bypasses), making the entire ATS UI unreachable.
 *
 * This self-heals existing installs by re-syncing the whole RBAC surface from
 * config: it upserts all configured permissions, re-grants the super-admin every
 * permission, and re-applies each existing workspace system role's configured set
 * (owner `*` = all permissions; admin now includes recruitment). Fresh installs
 * already get this through DatabaseSeeder, so running again here is harmless. The
 * Migrator tracks applied migrations by name, so this lower-numbered file still
 * runs on systems whose higher-numbered migrations are already applied.
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
