<?php

declare(strict_types=1);

use App\Core\Database;
use App\Core\Model;
use App\Services\Rbac\RbacManager;
use Database\Migration;

/**
 * Register the `automation` system module and re-sync RBAC so the new
 * `automation.view` / `automation.manage` permissions (config/rbac.php) become
 * live and grantable.
 *
 * The Workflow Automation Engine (docs/51) was service-only with no UI and no
 * permission to gate one. This adds the module (so the permissions can bind their
 * `module_id`) and runs the idempotent re-sync: `syncPermissions()` upserts the
 * two new permissions and links them to the module, then each system role's set is
 * re-applied (Owner `*` gains them automatically; the Administrator gains them via
 * its explicit config list; Member does not). Fresh installs pick this up via the
 * seeder; re-running here is harmless.
 */
return new class extends Migration {
    public function up(Database $db): void
    {
        if (! $db->table('system_modules')->where('key', '=', 'automation')->exists()) {
            $now = now();
            $db->table('system_modules')->insert([
                'uuid'        => Model::generateUuid(),
                'key'         => 'automation',
                'label'       => 'Automation',
                'description' => 'Workflow automation rules (trigger → conditions → actions).',
                'icon'        => 'workflow',
                'group'       => 'Intelligence',
                'is_active'   => 1,
                'sort_order'  => 23,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
        }

        (new RbacManager($db))->resyncSystemRolePermissions();
    }

    public function down(Database $db): void
    {
        // No-op: permissions are config-derived and idempotently re-synced forward;
        // rolling back must not strip access from live workspaces. The module row is
        // harmless if left in place.
    }
};
