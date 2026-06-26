<?php

declare(strict_types=1);

use App\Core\Database;
use Database\Migration;

/**
 * D1 — RBAC & Membership: blueprint extensions (FOREIGN KEYS).
 *
 * Paired FK migration for 0019_create_rbac_extensions.php. Adds every foreign
 * key for the new tables (policies, policy_permissions, permission_caches,
 * role_histories, permission_histories) AFTER all tables exist, so create-order
 * and cross-domain cycles never break the build. Each constraint is guarded by an
 * information_schema check (idempotent).
 *
 * FK targets are all BUILT / earlier-migration tables:
 *  - `workspaces`, `users`, `roles`, `permissions` (BUILT, 0001–0005/0016/0017)
 *  - `lookup_values` (0018) for the config-driven effect / action lookups
 *  - intra-domain `policies` (created in 0019)
 *
 * Polymorphic columns carry NO DB FK by design (Bible §6):
 *  - policies.subject_type/subject_id
 *  - permission_caches.principal_type/principal_id
 *  - role_histories.principal_type/principal_id
 *  - permission_histories.grantor_type/grantor_id
 */
return new class extends Migration {
    public function up(Database $db): void
    {
        // ---- policies ----------------------------------------------------
        $this->addFk($db, 'policies', 'policies_workspace_id_foreign', 'workspace_id', 'workspaces', 'CASCADE', 'CASCADE');
        $this->addFk($db, 'policies', 'policies_effect_id_foreign', 'effect_id', 'lookup_values', 'RESTRICT', 'CASCADE');
        $this->addFk($db, 'policies', 'policies_created_by_foreign', 'created_by', 'users', 'SET NULL', 'CASCADE');
        $this->addFk($db, 'policies', 'policies_updated_by_foreign', 'updated_by', 'users', 'SET NULL', 'CASCADE');

        // ---- policy_permissions ------------------------------------------
        $this->addFk($db, 'policy_permissions', 'policy_permissions_policy_id_foreign', 'policy_id', 'policies', 'CASCADE', 'CASCADE');
        $this->addFk($db, 'policy_permissions', 'policy_permissions_permission_id_foreign', 'permission_id', 'permissions', 'CASCADE', 'CASCADE');
        $this->addFk($db, 'policy_permissions', 'policy_permissions_created_by_foreign', 'created_by', 'users', 'SET NULL', 'CASCADE');

        // ---- permission_caches -------------------------------------------
        $this->addFk($db, 'permission_caches', 'permission_caches_workspace_id_foreign', 'workspace_id', 'workspaces', 'CASCADE', 'CASCADE');
        $this->addFk($db, 'permission_caches', 'permission_caches_permission_id_foreign', 'permission_id', 'permissions', 'CASCADE', 'CASCADE');

        // ---- role_histories ----------------------------------------------
        $this->addFk($db, 'role_histories', 'role_histories_workspace_id_foreign', 'workspace_id', 'workspaces', 'CASCADE', 'CASCADE');
        $this->addFk($db, 'role_histories', 'role_histories_role_id_foreign', 'role_id', 'roles', 'SET NULL', 'CASCADE');
        $this->addFk($db, 'role_histories', 'role_histories_action_id_foreign', 'action_id', 'lookup_values', 'RESTRICT', 'CASCADE');
        $this->addFk($db, 'role_histories', 'role_histories_performed_by_foreign', 'performed_by', 'users', 'SET NULL', 'CASCADE');

        // ---- permission_histories ----------------------------------------
        $this->addFk($db, 'permission_histories', 'permission_histories_workspace_id_foreign', 'workspace_id', 'workspaces', 'CASCADE', 'CASCADE');
        $this->addFk($db, 'permission_histories', 'permission_histories_permission_id_foreign', 'permission_id', 'permissions', 'SET NULL', 'CASCADE');
        $this->addFk($db, 'permission_histories', 'permission_histories_action_id_foreign', 'action_id', 'lookup_values', 'RESTRICT', 'CASCADE');
        $this->addFk($db, 'permission_histories', 'permission_histories_performed_by_foreign', 'performed_by', 'users', 'SET NULL', 'CASCADE');
    }

    public function down(Database $db): void
    {
        $fks = [
            'permission_histories' => [
                'permission_histories_workspace_id_foreign',
                'permission_histories_permission_id_foreign',
                'permission_histories_action_id_foreign',
                'permission_histories_performed_by_foreign',
            ],
            'role_histories' => [
                'role_histories_workspace_id_foreign',
                'role_histories_role_id_foreign',
                'role_histories_action_id_foreign',
                'role_histories_performed_by_foreign',
            ],
            'permission_caches' => [
                'permission_caches_workspace_id_foreign',
                'permission_caches_permission_id_foreign',
            ],
            'policy_permissions' => [
                'policy_permissions_policy_id_foreign',
                'policy_permissions_permission_id_foreign',
                'policy_permissions_created_by_foreign',
            ],
            'policies' => [
                'policies_workspace_id_foreign',
                'policies_effect_id_foreign',
                'policies_created_by_foreign',
                'policies_updated_by_foreign',
            ],
        ];

        foreach ($fks as $table => $constraints) {
            if (! $this->hasTable($db, $table)) {
                continue;
            }
            foreach ($constraints as $constraint) {
                if ($this->hasConstraint($db, $table, $constraint)) {
                    $db->unprepared("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$constraint}`");
                }
            }
        }
    }

    private function addFk(
        Database $db,
        string $table,
        string $constraint,
        string $column,
        string $refTable,
        string $onDelete,
        string $onUpdate
    ): void {
        if (! $this->hasTable($db, $table)) {
            return;
        }
        if ($this->hasConstraint($db, $table, $constraint)) {
            return;
        }
        $db->unprepared(
            "ALTER TABLE `{$table}` ADD CONSTRAINT `{$constraint}` "
            . "FOREIGN KEY (`{$column}`) REFERENCES `{$refTable}` (`id`) "
            . "ON DELETE {$onDelete} ON UPDATE {$onUpdate}"
        );
    }

    private function uuid(Database $db): string
    {
        return (string) $db->scalar('SELECT UUID()');
    }

    private function hasTable(Database $db, string $table): bool
    {
        return (int) $db->scalar(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [$table]
        ) > 0;
    }

    private function hasColumn(Database $db, string $table, string $column): bool
    {
        return (int) $db->scalar(
            'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        ) > 0;
    }

    private function hasConstraint(Database $db, string $table, string $constraint): bool
    {
        return (int) $db->scalar(
            'SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?',
            [$table, $constraint]
        ) > 0;
    }

    private function hasIndex(Database $db, string $table, string $index): bool
    {
        return (int) $db->scalar(
            'SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
            [$table, $index]
        ) > 0;
    }
};
