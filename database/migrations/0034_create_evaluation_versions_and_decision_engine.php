<?php

declare(strict_types=1);

use App\Core\Database;
use Database\Migration;

/**
 * AI Interview Engine — P2: Evaluation Templates & Decision Engine (docs/51 §9,
 * §3; D11 extension).
 *
 * DESIGN NOTE (anti-duplication, per the Continuous Project Audit Bible / docs 50):
 * the "Evaluation Template" of docs/51 §9 is the SAME concept as the existing
 * configurable scorecard built in D9 — `evaluation_forms` (the reusable, weighted,
 * thresholded rubric) + `evaluation_form_fields` (its weighted criteria). Rather
 * than create parallel `evaluation_templates`/`evaluation_template_criteria` tables
 * (which would fragment scoring across two systems), P2 REUSES those tables as the
 * template + criteria and adds only what is genuinely new:
 *
 *  1. `evaluation_form_versions` — an immutable JSON snapshot of a form + its fields
 *     at publish time, so the Decision Engine always scores against a frozen rubric
 *     and tenant edits never rewrite history (the spec's "template versioning").
 *  2. `decision_records` — the Decision Engine output: one aggregated, explainable
 *     decision for an interview/application against a frozen rubric version
 *     (overall + normalized score, pass/fail vs threshold, recommendation). This is
 *     distinct from `application_decisions` (which audits pipeline status moves).
 *  3. `decision_factors` — the per-criterion contribution breakdown that makes a
 *     decision explainable (raw × weight = weighted, with a rationale).
 *
 * Config-driven (no ENUMs; recommendation is a `lookup_values` FK), tenant-scoped
 * via `workspace_id`, uuid + timestamps, idempotent via information_schema guards.
 * Self-contained: all FK targets (workspaces, evaluation_forms, interviews,
 * applications, users, lookup_values) already exist.
 */
return new class extends Migration {
    public function up(Database $db): void
    {
        // 1) evaluation_form_versions — immutable published snapshots of a rubric.
        if (! $this->hasTable($db, 'evaluation_form_versions')) {
            $db->unprepared(
                "CREATE TABLE `evaluation_form_versions` (
                    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `uuid` CHAR(36) NOT NULL,
                    `workspace_id` BIGINT UNSIGNED NOT NULL,
                    `form_id` BIGINT UNSIGNED NOT NULL,
                    `version` INT UNSIGNED NOT NULL,
                    `snapshot` JSON NOT NULL,
                    `notes` VARCHAR(255) NULL,
                    `is_active` TINYINT(1) NOT NULL DEFAULT 0,
                    `published_by` BIGINT UNSIGNED NULL,
                    `published_at` TIMESTAMP NULL DEFAULT NULL,
                    `created_at` TIMESTAMP NULL DEFAULT NULL,
                    `updated_at` TIMESTAMP NULL DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `evaluation_form_versions_uuid_unique` (`uuid`),
                    UNIQUE KEY `evaluation_form_versions_form_version_unique` (`form_id`, `version`),
                    KEY `evaluation_form_versions_workspace_id_index` (`workspace_id`),
                    KEY `evaluation_form_versions_form_id_index` (`form_id`),
                    KEY `evaluation_form_versions_active_index` (`form_id`, `is_active`),
                    KEY `evaluation_form_versions_published_by_index` (`published_by`),
                    CONSTRAINT `evaluation_form_versions_workspace_id_foreign` FOREIGN KEY (`workspace_id`)
                        REFERENCES `workspaces` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                    CONSTRAINT `evaluation_form_versions_form_id_foreign` FOREIGN KEY (`form_id`)
                        REFERENCES `evaluation_forms` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                    CONSTRAINT `evaluation_form_versions_published_by_foreign` FOREIGN KEY (`published_by`)
                        REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }

        // 2) decision_records — the Decision Engine's aggregated, explainable output.
        if (! $this->hasTable($db, 'decision_records')) {
            $db->unprepared(
                "CREATE TABLE `decision_records` (
                    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `uuid` CHAR(36) NOT NULL,
                    `workspace_id` BIGINT UNSIGNED NOT NULL,
                    `interview_id` BIGINT UNSIGNED NULL,
                    `application_id` BIGINT UNSIGNED NULL,
                    `form_version_id` BIGINT UNSIGNED NULL,
                    `overall_score` DECIMAL(8,3) NULL,
                    `max_score` DECIMAL(8,3) NULL,
                    `normalized_score` DECIMAL(5,2) NULL,
                    `pass_threshold` DECIMAL(5,2) NULL,
                    `passed` TINYINT(1) NULL,
                    `recommendation_id` BIGINT UNSIGNED NULL,
                    `is_ai` TINYINT(1) NOT NULL DEFAULT 0,
                    `confidence` DECIMAL(5,2) NULL,
                    `summary` TEXT NULL,
                    `meta` JSON NULL,
                    `decided_by` BIGINT UNSIGNED NULL,
                    `decided_at` TIMESTAMP NULL DEFAULT NULL,
                    `created_at` TIMESTAMP NULL DEFAULT NULL,
                    `updated_at` TIMESTAMP NULL DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `decision_records_uuid_unique` (`uuid`),
                    KEY `decision_records_workspace_id_index` (`workspace_id`),
                    KEY `decision_records_interview_id_index` (`interview_id`),
                    KEY `decision_records_application_id_index` (`application_id`),
                    KEY `decision_records_form_version_id_index` (`form_version_id`),
                    KEY `decision_records_recommendation_id_index` (`recommendation_id`),
                    KEY `decision_records_decided_by_index` (`decided_by`),
                    CONSTRAINT `decision_records_workspace_id_foreign` FOREIGN KEY (`workspace_id`)
                        REFERENCES `workspaces` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                    CONSTRAINT `decision_records_interview_id_foreign` FOREIGN KEY (`interview_id`)
                        REFERENCES `interviews` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                    CONSTRAINT `decision_records_application_id_foreign` FOREIGN KEY (`application_id`)
                        REFERENCES `applications` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                    CONSTRAINT `decision_records_form_version_id_foreign` FOREIGN KEY (`form_version_id`)
                        REFERENCES `evaluation_form_versions` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                    CONSTRAINT `decision_records_recommendation_id_foreign` FOREIGN KEY (`recommendation_id`)
                        REFERENCES `lookup_values` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                    CONSTRAINT `decision_records_decided_by_foreign` FOREIGN KEY (`decided_by`)
                        REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }

        // 3) decision_factors — per-criterion contribution (explainable breakdown).
        if (! $this->hasTable($db, 'decision_factors')) {
            $db->unprepared(
                "CREATE TABLE `decision_factors` (
                    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `uuid` CHAR(36) NOT NULL,
                    `workspace_id` BIGINT UNSIGNED NOT NULL,
                    `decision_id` BIGINT UNSIGNED NOT NULL,
                    `criterion_key` VARCHAR(80) NOT NULL,
                    `criterion_label` VARCHAR(200) NULL,
                    `weight` DECIMAL(6,3) NOT NULL DEFAULT 0,
                    `raw_score` DECIMAL(8,3) NULL,
                    `max_score` DECIMAL(8,3) NULL,
                    `weighted_score` DECIMAL(8,3) NULL,
                    `rationale` TEXT NULL,
                    `sort_order` INT NOT NULL DEFAULT 0,
                    `created_at` TIMESTAMP NULL DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `decision_factors_uuid_unique` (`uuid`),
                    KEY `decision_factors_workspace_id_index` (`workspace_id`),
                    KEY `decision_factors_decision_id_index` (`decision_id`),
                    KEY `decision_factors_criterion_key_index` (`criterion_key`),
                    CONSTRAINT `decision_factors_workspace_id_foreign` FOREIGN KEY (`workspace_id`)
                        REFERENCES `workspaces` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                    CONSTRAINT `decision_factors_decision_id_foreign` FOREIGN KEY (`decision_id`)
                        REFERENCES `decision_records` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }
    }

    public function down(Database $db): void
    {
        $db->unprepared('DROP TABLE IF EXISTS `decision_factors`');
        $db->unprepared('DROP TABLE IF EXISTS `decision_records`');
        $db->unprepared('DROP TABLE IF EXISTS `evaluation_form_versions`');
    }

    private function hasTable(Database $db, string $table): bool
    {
        return (int) $db->scalar(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [$table]
        ) > 0;
    }
};
