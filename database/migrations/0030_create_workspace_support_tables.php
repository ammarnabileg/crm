<?php

declare(strict_types=1);

use App\Core\Database;
use Database\Migration;

/**
 * D2 support tables (docs/database/03 §9.2/§9.8/§9.9/§9.10) that the workspace
 * settings domain references but that no single domain "owned" in the inventory:
 * the config-driven per-entity status tables `workspace_statuses`,
 * `integration_status`, `domain_status`, `invitation_status`, plus the
 * marketplace `integrations` catalog. Creating them here lets the D2 FK migration
 * (0120) bind `workspace_integrations` / `workspace_domains` /
 * `workspace_invitations` to real tables (it guards each FK with hasTable).
 *
 * `workspace_statuses` also backs the cutover of the BUILT `workspaces.status`
 * ENUM to a `workspace_status_id` FK (done in the cutover migration). Each status
 * table carries the Bible §2.1 shape and is seeded with system defaults
 * (workspace_id NULL, is_system=1). Idempotent via information_schema guards.
 */
return new class extends Migration {
    /** The four per-entity status tables and their seeded system defaults. */
    private array $statusTables = [
        'workspace_statuses' => [
            // [key, label, color, default, initial, terminal]
            ['trial', 'Trial', '#f59e0b', 1, 1, 0],
            ['active', 'Active', '#16a34a', 0, 1, 0],
            ['suspended', 'Suspended', '#dc2626', 0, 0, 0],
            ['canceled', 'Canceled', '#6b7280', 0, 0, 1],
        ],
        'integration_status' => [
            ['pending', 'Pending', '#f59e0b', 1, 1, 0],
            ['connected', 'Connected', '#16a34a', 0, 0, 0],
            ['disconnected', 'Disconnected', '#6b7280', 0, 0, 0],
            ['error', 'Error', '#dc2626', 0, 0, 0],
        ],
        'domain_status' => [
            ['pending', 'Pending', '#f59e0b', 1, 1, 0],
            ['verifying', 'Verifying', '#3b82f6', 0, 0, 0],
            ['active', 'Active', '#16a34a', 0, 0, 0],
            ['failed', 'Failed', '#dc2626', 0, 0, 1],
        ],
        'invitation_status' => [
            ['pending', 'Pending', '#f59e0b', 1, 1, 0],
            ['accepted', 'Accepted', '#16a34a', 0, 0, 1],
            ['expired', 'Expired', '#6b7280', 0, 0, 1],
            ['revoked', 'Revoked', '#dc2626', 0, 0, 1],
        ],
    ];

    public function up(Database $db): void
    {
        foreach (array_keys($this->statusTables) as $table) {
            $this->createStatusTable($db, $table);
        }

        // integrations — global marketplace catalog (not tenant-scoped).
        if (! $this->hasTable($db, 'integrations')) {
            $db->unprepared(
                "CREATE TABLE `integrations` (
                    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `uuid` CHAR(36) NOT NULL,
                    `key` VARCHAR(60) NOT NULL,
                    `name` VARCHAR(120) NOT NULL,
                    `vendor` VARCHAR(120) NULL,
                    `description` VARCHAR(255) NULL,
                    `icon` VARCHAR(120) NULL,
                    `category` VARCHAR(60) NULL,
                    `scopes` JSON NULL,
                    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                    `sort_order` INT NOT NULL DEFAULT 0,
                    `created_at` TIMESTAMP NULL DEFAULT NULL,
                    `updated_at` TIMESTAMP NULL DEFAULT NULL,
                    `deleted_at` TIMESTAMP NULL DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `integrations_uuid_unique` (`uuid`),
                    UNIQUE KEY `integrations_key_unique` (`key`),
                    KEY `integrations_is_active_index` (`is_active`, `sort_order`),
                    KEY `integrations_deleted_at_index` (`deleted_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }
        $this->seedIntegrations($db);

        // The status tables' own genuine FK: workspace_id -> workspaces (workspaces
        // is BUILT, so this is safe inline here).
        foreach (array_keys($this->statusTables) as $table) {
            $fk = "{$table}_workspace_id_foreign";
            if (! $this->hasConstraint($db, $table, $fk)) {
                $db->unprepared(
                    "ALTER TABLE `{$table}` ADD CONSTRAINT `{$fk}`
                     FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`)
                     ON DELETE CASCADE ON UPDATE CASCADE"
                );
            }
        }
    }

    public function down(Database $db): void
    {
        foreach (array_keys($this->statusTables) as $table) {
            $db->unprepared("DROP TABLE IF EXISTS `{$table}`");
        }
        $db->unprepared('DROP TABLE IF EXISTS `integrations`');
    }

    private function createStatusTable(Database $db, string $table): void
    {
        if (! $this->hasTable($db, $table)) {
            $db->unprepared(
                "CREATE TABLE `{$table}` (
                    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `uuid` CHAR(36) NOT NULL,
                    `workspace_id` BIGINT UNSIGNED NULL,
                    `key` VARCHAR(60) NOT NULL,
                    `label` VARCHAR(120) NOT NULL,
                    `color` VARCHAR(20) NULL,
                    `sort_order` INT NOT NULL DEFAULT 0,
                    `is_default` TINYINT(1) NOT NULL DEFAULT 0,
                    `is_initial` TINYINT(1) NOT NULL DEFAULT 0,
                    `is_terminal` TINYINT(1) NOT NULL DEFAULT 0,
                    `is_system` TINYINT(1) NOT NULL DEFAULT 0,
                    `created_at` TIMESTAMP NULL DEFAULT NULL,
                    `updated_at` TIMESTAMP NULL DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `{$table}_uuid_unique` (`uuid`),
                    UNIQUE KEY `{$table}_workspace_key_unique` (`workspace_id`, `key`),
                    KEY `{$table}_workspace_id_index` (`workspace_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }

        $now = date('Y-m-d H:i:s');
        $order = 0;
        foreach ($this->statusTables[$table] as [$key, $label, $color, $isDefault, $isInitial, $isTerminal]) {
            $order++;
            $exists = $db->table($table)->whereNull('workspace_id')->where('key', '=', $key)->exists();
            if ($exists) {
                continue;
            }
            $db->table($table)->insert([
                'uuid'        => $this->uuid($db),
                'workspace_id' => null,
                'key'         => $key,
                'label'       => $label,
                'color'       => $color,
                'sort_order'  => $order,
                'is_default'  => $isDefault,
                'is_initial'  => $isInitial,
                'is_terminal' => $isTerminal,
                'is_system'   => 1,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
        }
    }

    private function seedIntegrations(Database $db): void
    {
        // [key, name, vendor, category, icon]
        $rows = [
            ['slack', 'Slack', 'Slack Technologies', 'communication', 'slack'],
            ['microsoft_teams', 'Microsoft Teams', 'Microsoft', 'communication', 'teams'],
            ['google_calendar', 'Google Calendar', 'Google', 'calendar', 'google'],
            ['zoom', 'Zoom', 'Zoom Video', 'video', 'zoom'],
            ['linkedin', 'LinkedIn', 'LinkedIn', 'sourcing', 'linkedin'],
        ];
        $now = date('Y-m-d H:i:s');
        $order = 0;
        foreach ($rows as [$key, $name, $vendor, $category, $icon]) {
            $order++;
            if ($db->table('integrations')->where('key', '=', $key)->exists()) {
                continue;
            }
            $db->table('integrations')->insert([
                'uuid'       => $this->uuid($db),
                'key'        => $key,
                'name'       => $name,
                'vendor'     => $vendor,
                'category'   => $category,
                'icon'       => $icon,
                'is_active'  => 1,
                'sort_order' => $order,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
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

    private function hasConstraint(Database $db, string $table, string $constraint): bool
    {
        return (int) $db->scalar(
            'SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?',
            [$table, $constraint]
        ) > 0;
    }
};
