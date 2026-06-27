<?php

declare(strict_types=1);

use App\Core\Database;
use Database\Migration;

/**
 * Workflow Automation Engine (docs/51; the Zapier/n8n-style layer) — P-Auto.
 *
 * An `automations` rule binds a TRIGGER event to an ordered list of CONDITION and
 * ACTION steps. When the event fires for a tenant, the AutomationEngine evaluates
 * the conditions and, if they pass, runs the actions — recording an
 * `automation_runs` row and a per-step `automation_run_steps` trace (the debugger /
 * execution log). `automation_versions` keeps every published version (compare /
 * rollback / restore / clone).
 *
 * Extensible by design (Open/Closed): triggers, conditions and actions are keys
 * resolved from a registry, so plugins add their own without touching core. Status
 * columns are code-validated VARCHARs (consistent with `ai_requests.status`) — no
 * ENUMs. Tenant-scoped via `workspace_id`, uuid + timestamps, idempotent guards.
 * Self-contained: FK targets (workspaces, users) already exist.
 */
return new class extends Migration {
    public function up(Database $db): void
    {
        if (! $this->hasTable($db, 'automations')) {
            $db->unprepared(
                "CREATE TABLE `automations` (
                    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `uuid` CHAR(36) NOT NULL,
                    `workspace_id` BIGINT UNSIGNED NOT NULL,
                    `name` VARCHAR(150) NOT NULL,
                    `slug` VARCHAR(160) NOT NULL,
                    `description` TEXT NULL,
                    `trigger_event` VARCHAR(80) NOT NULL,
                    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                    `version` INT UNSIGNED NOT NULL DEFAULT 0,
                    `created_by` BIGINT UNSIGNED NULL,
                    `created_at` TIMESTAMP NULL DEFAULT NULL,
                    `updated_at` TIMESTAMP NULL DEFAULT NULL,
                    `deleted_at` TIMESTAMP NULL DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `automations_uuid_unique` (`uuid`),
                    UNIQUE KEY `automations_workspace_slug_unique` (`workspace_id`, `slug`),
                    KEY `automations_workspace_id_index` (`workspace_id`),
                    KEY `automations_trigger_index` (`workspace_id`, `trigger_event`, `is_active`),
                    KEY `automations_created_by_index` (`created_by`),
                    KEY `automations_deleted_at_index` (`deleted_at`),
                    CONSTRAINT `automations_workspace_id_foreign` FOREIGN KEY (`workspace_id`)
                        REFERENCES `workspaces` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                    CONSTRAINT `automations_created_by_foreign` FOREIGN KEY (`created_by`)
                        REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }

        if (! $this->hasTable($db, 'automation_steps')) {
            $db->unprepared(
                "CREATE TABLE `automation_steps` (
                    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `uuid` CHAR(36) NOT NULL,
                    `workspace_id` BIGINT UNSIGNED NOT NULL,
                    `automation_id` BIGINT UNSIGNED NOT NULL,
                    `step_type` VARCHAR(20) NOT NULL,
                    `key` VARCHAR(80) NOT NULL,
                    `config` JSON NULL,
                    `sort_order` INT NOT NULL DEFAULT 0,
                    `created_at` TIMESTAMP NULL DEFAULT NULL,
                    `updated_at` TIMESTAMP NULL DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `automation_steps_uuid_unique` (`uuid`),
                    KEY `automation_steps_workspace_id_index` (`workspace_id`),
                    KEY `automation_steps_automation_id_index` (`automation_id`),
                    KEY `automation_steps_order_index` (`automation_id`, `sort_order`),
                    CONSTRAINT `automation_steps_workspace_id_foreign` FOREIGN KEY (`workspace_id`)
                        REFERENCES `workspaces` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                    CONSTRAINT `automation_steps_automation_id_foreign` FOREIGN KEY (`automation_id`)
                        REFERENCES `automations` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }

        if (! $this->hasTable($db, 'automation_versions')) {
            $db->unprepared(
                "CREATE TABLE `automation_versions` (
                    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `uuid` CHAR(36) NOT NULL,
                    `workspace_id` BIGINT UNSIGNED NOT NULL,
                    `automation_id` BIGINT UNSIGNED NOT NULL,
                    `version` INT UNSIGNED NOT NULL,
                    `snapshot` JSON NOT NULL,
                    `notes` VARCHAR(255) NULL,
                    `is_active` TINYINT(1) NOT NULL DEFAULT 0,
                    `published_by` BIGINT UNSIGNED NULL,
                    `published_at` TIMESTAMP NULL DEFAULT NULL,
                    `created_at` TIMESTAMP NULL DEFAULT NULL,
                    `updated_at` TIMESTAMP NULL DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `automation_versions_uuid_unique` (`uuid`),
                    UNIQUE KEY `automation_versions_automation_version_unique` (`automation_id`, `version`),
                    KEY `automation_versions_workspace_id_index` (`workspace_id`),
                    KEY `automation_versions_automation_id_index` (`automation_id`),
                    KEY `automation_versions_active_index` (`automation_id`, `is_active`),
                    KEY `automation_versions_published_by_index` (`published_by`),
                    CONSTRAINT `automation_versions_workspace_id_foreign` FOREIGN KEY (`workspace_id`)
                        REFERENCES `workspaces` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                    CONSTRAINT `automation_versions_automation_id_foreign` FOREIGN KEY (`automation_id`)
                        REFERENCES `automations` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                    CONSTRAINT `automation_versions_published_by_foreign` FOREIGN KEY (`published_by`)
                        REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }

        if (! $this->hasTable($db, 'automation_runs')) {
            $db->unprepared(
                "CREATE TABLE `automation_runs` (
                    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `uuid` CHAR(36) NOT NULL,
                    `workspace_id` BIGINT UNSIGNED NOT NULL,
                    `automation_id` BIGINT UNSIGNED NOT NULL,
                    `trigger_event` VARCHAR(80) NOT NULL,
                    `status` VARCHAR(20) NOT NULL DEFAULT 'running',
                    `context` JSON NULL,
                    `error` TEXT NULL,
                    `started_at` TIMESTAMP NULL DEFAULT NULL,
                    `ended_at` TIMESTAMP NULL DEFAULT NULL,
                    `duration_ms` INT UNSIGNED NULL,
                    `created_at` TIMESTAMP NULL DEFAULT NULL,
                    `updated_at` TIMESTAMP NULL DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `automation_runs_uuid_unique` (`uuid`),
                    KEY `automation_runs_workspace_id_index` (`workspace_id`),
                    KEY `automation_runs_automation_id_index` (`automation_id`),
                    KEY `automation_runs_status_index` (`workspace_id`, `status`),
                    KEY `automation_runs_created_at_index` (`created_at`),
                    CONSTRAINT `automation_runs_workspace_id_foreign` FOREIGN KEY (`workspace_id`)
                        REFERENCES `workspaces` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                    CONSTRAINT `automation_runs_automation_id_foreign` FOREIGN KEY (`automation_id`)
                        REFERENCES `automations` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }

        if (! $this->hasTable($db, 'automation_run_steps')) {
            $db->unprepared(
                "CREATE TABLE `automation_run_steps` (
                    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `uuid` CHAR(36) NOT NULL,
                    `workspace_id` BIGINT UNSIGNED NOT NULL,
                    `run_id` BIGINT UNSIGNED NOT NULL,
                    `step_type` VARCHAR(20) NOT NULL,
                    `step_key` VARCHAR(80) NOT NULL,
                    `status` VARCHAR(20) NOT NULL,
                    `sequence` INT NOT NULL DEFAULT 0,
                    `input` JSON NULL,
                    `output` JSON NULL,
                    `error` TEXT NULL,
                    `duration_ms` INT UNSIGNED NULL,
                    `created_at` TIMESTAMP NULL DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `automation_run_steps_uuid_unique` (`uuid`),
                    KEY `automation_run_steps_workspace_id_index` (`workspace_id`),
                    KEY `automation_run_steps_run_id_index` (`run_id`),
                    KEY `automation_run_steps_run_seq_index` (`run_id`, `sequence`),
                    CONSTRAINT `automation_run_steps_workspace_id_foreign` FOREIGN KEY (`workspace_id`)
                        REFERENCES `workspaces` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                    CONSTRAINT `automation_run_steps_run_id_foreign` FOREIGN KEY (`run_id`)
                        REFERENCES `automation_runs` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }
    }

    public function down(Database $db): void
    {
        foreach (['automation_run_steps', 'automation_runs', 'automation_versions', 'automation_steps', 'automations'] as $t) {
            $db->unprepared("DROP TABLE IF EXISTS `{$t}`");
        }
    }

    private function hasTable(Database $db, string $table): bool
    {
        return (int) $db->scalar(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [$table]
        ) > 0;
    }
};
