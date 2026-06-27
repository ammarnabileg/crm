<?php

declare(strict_types=1);

use App\Core\Database;
use Database\Migration;

/**
 * AI Interview Engine — Question Bank Engine (docs/51 §10).
 *
 * DESIGN NOTE (anti-duplication, per the Continuous Project Audit Bible / docs 50):
 * the Question BANK is a REUSABLE library of interview questions, distinct from the
 * existing D7 `interview_questions` table (which records the questions actually asked
 * during a specific interview). The bank lets a tenant curate a tagged, classified,
 * multi-source pool that the engine draws from when assembling an interview — blending
 * `static` (hand-authored), `company` (tenant/role specific) and `ai` (generated)
 * questions (docs/51 §10's static + AI + company mixing).
 *
 * Config-driven (no ENUMs — question_type / difficulty / source are VARCHAR keys
 * validated in code), tenant-scoped via `workspace_id`, uuid + timestamps, idempotent
 * via information_schema guards (runs OUTSIDE a transaction). Self-contained: all FK
 * targets (workspaces, users) already exist.
 *
 *  1. `question_bank`           — the reusable, classified, taggable question.
 *  2. `question_tags`           — per-tenant tag dictionary (key + label).
 *  3. `question_taggables`      — many-to-many pivot question <-> tag.
 *  4. `question_reference_answers` — model / sample answers + scoring hints.
 *  5. `question_follow_ups`     — conditional follow-up prompts (text or another
 *                                 bank question), with an optional trigger condition.
 */
return new class extends Migration {
    public function up(Database $db): void
    {
        // 1) question_bank — the reusable, classified question.
        if (! $this->hasTable($db, 'question_bank')) {
            $db->unprepared(
                "CREATE TABLE `question_bank` (
                    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `uuid` CHAR(36) NOT NULL,
                    `workspace_id` BIGINT UNSIGNED NOT NULL,
                    `question_type` VARCHAR(40) NOT NULL,
                    `text` TEXT NOT NULL,
                    `difficulty` VARCHAR(20) NULL,
                    `language` VARCHAR(10) NULL,
                    `job_family` VARCHAR(60) NULL,
                    `department` VARCHAR(80) NULL,
                    `source` VARCHAR(20) NOT NULL DEFAULT 'static',
                    `expected_skills` JSON NULL,
                    `competency` VARCHAR(80) NULL,
                    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                    `created_by` BIGINT UNSIGNED NULL,
                    `deleted_at` TIMESTAMP NULL DEFAULT NULL,
                    `created_at` TIMESTAMP NULL DEFAULT NULL,
                    `updated_at` TIMESTAMP NULL DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `question_bank_uuid_unique` (`uuid`),
                    KEY `question_bank_workspace_id_index` (`workspace_id`),
                    KEY `question_bank_workspace_type_index` (`workspace_id`, `question_type`),
                    KEY `question_bank_workspace_difficulty_index` (`workspace_id`, `difficulty`),
                    KEY `question_bank_workspace_language_index` (`workspace_id`, `language`),
                    KEY `question_bank_workspace_source_index` (`workspace_id`, `source`),
                    KEY `question_bank_created_by_index` (`created_by`),
                    CONSTRAINT `question_bank_workspace_id_foreign` FOREIGN KEY (`workspace_id`)
                        REFERENCES `workspaces` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                    CONSTRAINT `question_bank_created_by_foreign` FOREIGN KEY (`created_by`)
                        REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }

        // 2) question_tags — per-tenant tag dictionary.
        if (! $this->hasTable($db, 'question_tags')) {
            $db->unprepared(
                "CREATE TABLE `question_tags` (
                    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `uuid` CHAR(36) NOT NULL,
                    `workspace_id` BIGINT UNSIGNED NOT NULL,
                    `key` VARCHAR(60) NOT NULL,
                    `label` VARCHAR(120) NOT NULL,
                    `created_at` TIMESTAMP NULL DEFAULT NULL,
                    `updated_at` TIMESTAMP NULL DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `question_tags_uuid_unique` (`uuid`),
                    UNIQUE KEY `question_tags_workspace_key_unique` (`workspace_id`, `key`),
                    KEY `question_tags_workspace_id_index` (`workspace_id`),
                    CONSTRAINT `question_tags_workspace_id_foreign` FOREIGN KEY (`workspace_id`)
                        REFERENCES `workspaces` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }

        // 3) question_taggables — many-to-many pivot (no model needed).
        if (! $this->hasTable($db, 'question_taggables')) {
            $db->unprepared(
                "CREATE TABLE `question_taggables` (
                    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `uuid` CHAR(36) NOT NULL,
                    `workspace_id` BIGINT UNSIGNED NOT NULL,
                    `question_id` BIGINT UNSIGNED NOT NULL,
                    `tag_id` BIGINT UNSIGNED NOT NULL,
                    `created_at` TIMESTAMP NULL DEFAULT NULL,
                    `updated_at` TIMESTAMP NULL DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `question_taggables_uuid_unique` (`uuid`),
                    UNIQUE KEY `question_taggables_question_tag_unique` (`question_id`, `tag_id`),
                    KEY `question_taggables_workspace_id_index` (`workspace_id`),
                    KEY `question_taggables_question_id_index` (`question_id`),
                    KEY `question_taggables_tag_id_index` (`tag_id`),
                    CONSTRAINT `question_taggables_workspace_id_foreign` FOREIGN KEY (`workspace_id`)
                        REFERENCES `workspaces` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                    CONSTRAINT `question_taggables_question_id_foreign` FOREIGN KEY (`question_id`)
                        REFERENCES `question_bank` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                    CONSTRAINT `question_taggables_tag_id_foreign` FOREIGN KEY (`tag_id`)
                        REFERENCES `question_tags` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }

        // 4) question_reference_answers — model / sample answers + scoring hints.
        if (! $this->hasTable($db, 'question_reference_answers')) {
            $db->unprepared(
                "CREATE TABLE `question_reference_answers` (
                    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `uuid` CHAR(36) NOT NULL,
                    `workspace_id` BIGINT UNSIGNED NOT NULL,
                    `question_id` BIGINT UNSIGNED NOT NULL,
                    `answer` TEXT NOT NULL,
                    `is_model_answer` TINYINT(1) NOT NULL DEFAULT 0,
                    `score_hint` DECIMAL(5,2) NULL,
                    `notes` VARCHAR(255) NULL,
                    `created_at` TIMESTAMP NULL DEFAULT NULL,
                    `updated_at` TIMESTAMP NULL DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `question_reference_answers_uuid_unique` (`uuid`),
                    KEY `question_reference_answers_workspace_id_index` (`workspace_id`),
                    KEY `question_reference_answers_question_id_index` (`question_id`),
                    CONSTRAINT `question_reference_answers_workspace_id_foreign` FOREIGN KEY (`workspace_id`)
                        REFERENCES `workspaces` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                    CONSTRAINT `question_reference_answers_question_id_foreign` FOREIGN KEY (`question_id`)
                        REFERENCES `question_bank` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }

        // 5) question_follow_ups — conditional follow-up prompts.
        if (! $this->hasTable($db, 'question_follow_ups')) {
            $db->unprepared(
                "CREATE TABLE `question_follow_ups` (
                    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `uuid` CHAR(36) NOT NULL,
                    `workspace_id` BIGINT UNSIGNED NOT NULL,
                    `question_id` BIGINT UNSIGNED NOT NULL,
                    `follow_up_text` TEXT NULL,
                    `follow_up_question_id` BIGINT UNSIGNED NULL,
                    `condition` JSON NULL,
                    `sort_order` INT NOT NULL DEFAULT 0,
                    `created_at` TIMESTAMP NULL DEFAULT NULL,
                    `updated_at` TIMESTAMP NULL DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `question_follow_ups_uuid_unique` (`uuid`),
                    KEY `question_follow_ups_workspace_id_index` (`workspace_id`),
                    KEY `question_follow_ups_question_id_index` (`question_id`),
                    KEY `question_follow_ups_follow_up_question_id_index` (`follow_up_question_id`),
                    CONSTRAINT `question_follow_ups_workspace_id_foreign` FOREIGN KEY (`workspace_id`)
                        REFERENCES `workspaces` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                    CONSTRAINT `question_follow_ups_question_id_foreign` FOREIGN KEY (`question_id`)
                        REFERENCES `question_bank` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                    CONSTRAINT `question_follow_ups_follow_up_question_id_foreign` FOREIGN KEY (`follow_up_question_id`)
                        REFERENCES `question_bank` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }
    }

    public function down(Database $db): void
    {
        $db->unprepared('DROP TABLE IF EXISTS `question_follow_ups`');
        $db->unprepared('DROP TABLE IF EXISTS `question_reference_answers`');
        $db->unprepared('DROP TABLE IF EXISTS `question_taggables`');
        $db->unprepared('DROP TABLE IF EXISTS `question_tags`');
        $db->unprepared('DROP TABLE IF EXISTS `question_bank`');
    }

    private function hasTable(Database $db, string $table): bool
    {
        return (int) $db->scalar(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [$table]
        ) > 0;
    }
};
