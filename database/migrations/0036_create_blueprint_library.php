<?php

declare(strict_types=1);

use App\Core\Database;
use Database\Migration;

/**
 * AI Interview Engine — Blueprint Engine + Blueprint Library (docs/51 §6, §11).
 *
 * A "blueprint" is the reusable, role-family interview template that drives the
 * engine: an ordered set of weighted SECTIONS (e.g. Technical Assessment, Problem
 * Solving, Behavioral), each carrying RULES (question strategy, scoring guidance,
 * follow-up policy, expected skills/behaviors, evaluation criteria) that the
 * interview runtime consults to generate questions and steer the conversation.
 *
 * The Blueprint Library (config/blueprints.php) ships ready-made blueprints per
 * role family that a tenant INSTANTIATES into its own editable blueprint
 * (App\Services\Blueprint\BlueprintLibrary). The Blueprint Engine
 * (App\Services\Blueprint\BlueprintEngine) PUBLISHES immutable JSON version
 * snapshots — exactly like the Evaluation Template / Workflow versioning of
 * migration 0034/0035 — so the runtime always executes against a frozen blueprint
 * and tenant edits never rewrite history.
 *
 * This migration adds four tables:
 *  1. `interview_blueprints`        — the blueprint header (name, role_family, …).
 *  2. `blueprint_sections`          — its ordered, weighted sections.
 *  3. `blueprint_section_rules`     — typed rules attached to a section.
 *  4. `blueprint_versions`          — immutable published snapshots.
 *
 * Conventions: no ENUM columns (role_family/difficulty/rule_type are VARCHAR keys
 * validated in code); tenant-scoped via `workspace_id`; uuid + timestamps; every FK
 * indexed; idempotent via information_schema guards; runs OUTSIDE a transaction
 * (MySQL DDL auto-commits). All FK targets (workspaces, users) already exist.
 */
return new class extends Migration {
    public function up(Database $db): void
    {
        // 1) interview_blueprints — the role-family blueprint header.
        if (! $this->hasTable($db, 'interview_blueprints')) {
            $db->unprepared(
                "CREATE TABLE `interview_blueprints` (
                    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `uuid` CHAR(36) NOT NULL,
                    `workspace_id` BIGINT UNSIGNED NOT NULL,
                    `name` VARCHAR(150) NOT NULL,
                    `slug` VARCHAR(160) NOT NULL,
                    `role_family` VARCHAR(60) NOT NULL,
                    `description` TEXT NULL,
                    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                    `version` INT UNSIGNED NOT NULL DEFAULT 0,
                    `created_by` BIGINT UNSIGNED NULL,
                    `deleted_at` TIMESTAMP NULL DEFAULT NULL,
                    `created_at` TIMESTAMP NULL DEFAULT NULL,
                    `updated_at` TIMESTAMP NULL DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `interview_blueprints_uuid_unique` (`uuid`),
                    UNIQUE KEY `interview_blueprints_workspace_slug_unique` (`workspace_id`, `slug`),
                    KEY `interview_blueprints_workspace_id_index` (`workspace_id`),
                    KEY `interview_blueprints_role_family_index` (`role_family`),
                    KEY `interview_blueprints_created_by_index` (`created_by`),
                    CONSTRAINT `interview_blueprints_workspace_id_foreign` FOREIGN KEY (`workspace_id`)
                        REFERENCES `workspaces` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                    CONSTRAINT `interview_blueprints_created_by_foreign` FOREIGN KEY (`created_by`)
                        REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }

        // 2) blueprint_sections — ordered, weighted sections of a blueprint.
        if (! $this->hasTable($db, 'blueprint_sections')) {
            $db->unprepared(
                "CREATE TABLE `blueprint_sections` (
                    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `uuid` CHAR(36) NOT NULL,
                    `workspace_id` BIGINT UNSIGNED NOT NULL,
                    `blueprint_id` BIGINT UNSIGNED NOT NULL,
                    `key` VARCHAR(60) NOT NULL,
                    `title` VARCHAR(150) NOT NULL,
                    `objective` TEXT NULL,
                    `weight` DECIMAL(6,3) NOT NULL DEFAULT 0,
                    `difficulty` VARCHAR(20) NULL,
                    `config` JSON NULL,
                    `sort_order` INT NOT NULL DEFAULT 0,
                    `created_at` TIMESTAMP NULL DEFAULT NULL,
                    `updated_at` TIMESTAMP NULL DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `blueprint_sections_uuid_unique` (`uuid`),
                    UNIQUE KEY `blueprint_sections_blueprint_key_unique` (`blueprint_id`, `key`),
                    KEY `blueprint_sections_workspace_id_index` (`workspace_id`),
                    KEY `blueprint_sections_blueprint_id_index` (`blueprint_id`),
                    CONSTRAINT `blueprint_sections_workspace_id_foreign` FOREIGN KEY (`workspace_id`)
                        REFERENCES `workspaces` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                    CONSTRAINT `blueprint_sections_blueprint_id_foreign` FOREIGN KEY (`blueprint_id`)
                        REFERENCES `interview_blueprints` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }

        // 3) blueprint_section_rules — typed rules attached to a section.
        if (! $this->hasTable($db, 'blueprint_section_rules')) {
            $db->unprepared(
                "CREATE TABLE `blueprint_section_rules` (
                    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `uuid` CHAR(36) NOT NULL,
                    `workspace_id` BIGINT UNSIGNED NOT NULL,
                    `section_id` BIGINT UNSIGNED NOT NULL,
                    `rule_type` VARCHAR(40) NOT NULL,
                    `payload` JSON NULL,
                    `sort_order` INT NOT NULL DEFAULT 0,
                    `created_at` TIMESTAMP NULL DEFAULT NULL,
                    `updated_at` TIMESTAMP NULL DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `blueprint_section_rules_uuid_unique` (`uuid`),
                    KEY `blueprint_section_rules_workspace_id_index` (`workspace_id`),
                    KEY `blueprint_section_rules_section_id_index` (`section_id`),
                    KEY `blueprint_section_rules_rule_type_index` (`rule_type`),
                    CONSTRAINT `blueprint_section_rules_workspace_id_foreign` FOREIGN KEY (`workspace_id`)
                        REFERENCES `workspaces` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                    CONSTRAINT `blueprint_section_rules_section_id_foreign` FOREIGN KEY (`section_id`)
                        REFERENCES `blueprint_sections` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }

        // 4) blueprint_versions — immutable published snapshots.
        if (! $this->hasTable($db, 'blueprint_versions')) {
            $db->unprepared(
                "CREATE TABLE `blueprint_versions` (
                    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `uuid` CHAR(36) NOT NULL,
                    `workspace_id` BIGINT UNSIGNED NOT NULL,
                    `blueprint_id` BIGINT UNSIGNED NOT NULL,
                    `version` INT UNSIGNED NOT NULL,
                    `snapshot` JSON NOT NULL,
                    `notes` VARCHAR(255) NULL,
                    `is_active` TINYINT(1) NOT NULL DEFAULT 0,
                    `published_by` BIGINT UNSIGNED NULL,
                    `published_at` TIMESTAMP NULL DEFAULT NULL,
                    `created_at` TIMESTAMP NULL DEFAULT NULL,
                    `updated_at` TIMESTAMP NULL DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `blueprint_versions_uuid_unique` (`uuid`),
                    UNIQUE KEY `blueprint_versions_blueprint_version_unique` (`blueprint_id`, `version`),
                    KEY `blueprint_versions_workspace_id_index` (`workspace_id`),
                    KEY `blueprint_versions_blueprint_id_index` (`blueprint_id`),
                    KEY `blueprint_versions_active_index` (`blueprint_id`, `is_active`),
                    KEY `blueprint_versions_published_by_index` (`published_by`),
                    CONSTRAINT `blueprint_versions_workspace_id_foreign` FOREIGN KEY (`workspace_id`)
                        REFERENCES `workspaces` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                    CONSTRAINT `blueprint_versions_blueprint_id_foreign` FOREIGN KEY (`blueprint_id`)
                        REFERENCES `interview_blueprints` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                    CONSTRAINT `blueprint_versions_published_by_foreign` FOREIGN KEY (`published_by`)
                        REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }
    }

    public function down(Database $db): void
    {
        // Drop in reverse FK dependency order.
        $db->unprepared('DROP TABLE IF EXISTS `blueprint_versions`');
        $db->unprepared('DROP TABLE IF EXISTS `blueprint_section_rules`');
        $db->unprepared('DROP TABLE IF EXISTS `blueprint_sections`');
        $db->unprepared('DROP TABLE IF EXISTS `interview_blueprints`');
    }

    private function hasTable(Database $db, string $table): bool
    {
        return (int) $db->scalar(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [$table]
        ) > 0;
    }
};
