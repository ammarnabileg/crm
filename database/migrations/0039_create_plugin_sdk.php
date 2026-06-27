<?php

declare(strict_types=1);

use App\Core\Database;
use Database\Migration;

/**
 * Plugin SDK registry (docs/51 Extensibility / Plugin SDK).
 *
 * `plugins` is the PLATFORM registry of installed plugin packages (name, author,
 * version, manifest, dependencies, declared permissions, license, min platform
 * version, lifecycle status). It is platform-level (no `workspace_id`, like
 * `ai_providers`/`system_modules`). `workspace_plugins` records per-tenant
 * enablement + settings so a workspace can turn a marketplace plugin on/off without
 * affecting others.
 *
 * Plugins extend the system ONLY through the PluginApi capability gateway (the
 * sandbox) — they never touch the DB/secrets/AI-keys directly. Status is a
 * code-validated VARCHAR (installed|enabled|disabled|uninstalled) — no ENUMs.
 * Idempotent guards; self-contained (FK target `workspaces` exists).
 */
return new class extends Migration {
    public function up(Database $db): void
    {
        if (! $this->hasTable($db, 'plugins')) {
            $db->unprepared(
                "CREATE TABLE `plugins` (
                    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `uuid` CHAR(36) NOT NULL,
                    `key` VARCHAR(80) NOT NULL,
                    `name` VARCHAR(150) NOT NULL,
                    `author` VARCHAR(150) NULL,
                    `version` VARCHAR(20) NOT NULL DEFAULT '1.0.0',
                    `description` TEXT NULL,
                    `status` VARCHAR(20) NOT NULL DEFAULT 'installed',
                    `manifest` JSON NULL,
                    `dependencies` JSON NULL,
                    `permissions` JSON NULL,
                    `license` VARCHAR(40) NULL,
                    `min_platform_version` VARCHAR(20) NULL,
                    `installed_at` TIMESTAMP NULL DEFAULT NULL,
                    `enabled_at` TIMESTAMP NULL DEFAULT NULL,
                    `created_at` TIMESTAMP NULL DEFAULT NULL,
                    `updated_at` TIMESTAMP NULL DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `plugins_uuid_unique` (`uuid`),
                    UNIQUE KEY `plugins_key_unique` (`key`),
                    KEY `plugins_status_index` (`status`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }

        if (! $this->hasTable($db, 'workspace_plugins')) {
            $db->unprepared(
                "CREATE TABLE `workspace_plugins` (
                    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `uuid` CHAR(36) NOT NULL,
                    `workspace_id` BIGINT UNSIGNED NOT NULL,
                    `plugin_id` BIGINT UNSIGNED NOT NULL,
                    `is_enabled` TINYINT(1) NOT NULL DEFAULT 0,
                    `settings` JSON NULL,
                    `enabled_at` TIMESTAMP NULL DEFAULT NULL,
                    `created_at` TIMESTAMP NULL DEFAULT NULL,
                    `updated_at` TIMESTAMP NULL DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `workspace_plugins_uuid_unique` (`uuid`),
                    UNIQUE KEY `workspace_plugins_workspace_plugin_unique` (`workspace_id`, `plugin_id`),
                    KEY `workspace_plugins_workspace_id_index` (`workspace_id`),
                    KEY `workspace_plugins_plugin_id_index` (`plugin_id`),
                    CONSTRAINT `workspace_plugins_workspace_id_foreign` FOREIGN KEY (`workspace_id`)
                        REFERENCES `workspaces` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                    CONSTRAINT `workspace_plugins_plugin_id_foreign` FOREIGN KEY (`plugin_id`)
                        REFERENCES `plugins` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }
    }

    public function down(Database $db): void
    {
        $db->unprepared('DROP TABLE IF EXISTS `workspace_plugins`');
        $db->unprepared('DROP TABLE IF EXISTS `plugins`');
    }

    private function hasTable(Database $db, string $table): bool
    {
        return (int) $db->scalar(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [$table]
        ) > 0;
    }
};
