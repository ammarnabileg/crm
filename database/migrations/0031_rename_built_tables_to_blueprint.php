<?php

declare(strict_types=1);

use App\Core\Database;
use Database\Migration;

/**
 * Built→blueprint table renames (docs/database/00 inventory + 12 Cutover):
 *   activity_log     -> activity_logs
 *   permission_role  -> role_permissions
 *   membership_role  -> membership_roles
 *   user_role        -> user_roles
 *   ai_credentials   -> tenant_ai_keys
 *
 * MySQL preserves each table's own FK definitions across a RENAME (and updates any
 * FK that REFERENCES a renamed table), so this is data-safe. After the AI-keys
 * rename we bind the one deferred FK that targeted the future name:
 * workspace_ai_settings.default_key_id -> tenant_ai_keys. Code symbols are updated
 * in the same change. Idempotent via information_schema guards.
 */
return new class extends Migration {
    /** old => new */
    private array $renames = [
        'activity_log'    => 'activity_logs',
        'permission_role' => 'role_permissions',
        'membership_role' => 'membership_roles',
        'user_role'       => 'user_roles',
        'ai_credentials'  => 'tenant_ai_keys',
    ];

    public function up(Database $db): void
    {
        foreach ($this->renames as $old => $new) {
            if ($this->hasTable($db, $old) && ! $this->hasTable($db, $new)) {
                $db->unprepared("RENAME TABLE `{$old}` TO `{$new}`");
            }
        }

        // Bind the FK that was deferred because its target was still named
        // ai_credentials when the D2 FK migration ran.
        if (
            $this->hasTable($db, 'tenant_ai_keys')
            && $this->hasColumn($db, 'workspace_ai_settings', 'default_key_id')
            && ! $this->hasConstraint($db, 'workspace_ai_settings', 'workspace_ai_settings_default_key_id_foreign')
        ) {
            $db->unprepared(
                'ALTER TABLE `workspace_ai_settings` ADD CONSTRAINT `workspace_ai_settings_default_key_id_foreign`
                 FOREIGN KEY (`default_key_id`) REFERENCES `tenant_ai_keys` (`id`)
                 ON DELETE SET NULL ON UPDATE CASCADE'
            );
        }
    }

    public function down(Database $db): void
    {
        if ($this->hasConstraint($db, 'workspace_ai_settings', 'workspace_ai_settings_default_key_id_foreign')) {
            $db->unprepared('ALTER TABLE `workspace_ai_settings` DROP FOREIGN KEY `workspace_ai_settings_default_key_id_foreign`');
        }

        foreach ($this->renames as $old => $new) {
            if ($this->hasTable($db, $new) && ! $this->hasTable($db, $old)) {
                $db->unprepared("RENAME TABLE `{$new}` TO `{$old}`");
            }
        }
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
};
