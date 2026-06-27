<?php

declare(strict_types=1);

use App\Core\Database;
use Database\Migration;

/**
 * AI Interview Engine — P10: Learning Engine + Human-in-the-Loop reviews
 * (docs/51 §18 Continuous Learning, §20; Human-in-the-Loop oversight).
 *
 * DESIGN NOTE (anti-duplication, per the Continuous Project Audit Bible / docs 50):
 * Human-in-the-Loop reviews target the EXISTING Decision Engine output built in P2 —
 * `decision_records` (migration 0034). Rather than fork a parallel decision store,
 * P10 adds only what is genuinely new:
 *
 *  1. `decision_reviews` — an append-only log of human oversight actions taken on a
 *     decision (approve / edit / reject / request_changes), each with a reason and,
 *     for an edit, the exact field changes that were applied. This is the audit trail
 *     of the "human in the loop" overriding or endorsing an AI/automated decision.
 *  2. `learning_feedback` — the captured signal the engine will LATER learn from:
 *     human corrections, real-world outcomes, and system observations, tied to an
 *     interview and/or a decision, with an optional rating + structured correction.
 *     No model retraining happens now — P10 only captures + aggregates, strictly
 *     within the tenant boundary (a tenant never learns from another tenant's data).
 *
 * Additionally, a nullable review state is added to `decision_records` so a decision
 * can carry its latest review verdict inline (review_status / reviewed_at /
 * reviewed_by). This is purely ADDITIVE and backward-compatible: the columns are
 * added only if the table exists and the columns do not already exist, via guarded
 * ALTERs — the original 0034 migration is NOT touched.
 *
 * Config-driven (no ENUMs; action/source are VARCHAR), tenant-scoped via
 * `workspace_id`, uuid + timestamps, idempotent via information_schema guards.
 * Self-contained: all FK targets (workspaces, decision_records, interviews, users)
 * already exist.
 */
return new class extends Migration {
    public function up(Database $db): void
    {
        // 1) decision_reviews — append-only human oversight log on a decision.
        if (! $this->hasTable($db, 'decision_reviews')) {
            $db->unprepared(
                "CREATE TABLE `decision_reviews` (
                    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `uuid` CHAR(36) NOT NULL,
                    `workspace_id` BIGINT UNSIGNED NOT NULL,
                    `decision_id` BIGINT UNSIGNED NOT NULL,
                    `reviewer_id` BIGINT UNSIGNED NULL,
                    `action` VARCHAR(30) NOT NULL,
                    `reason` TEXT NULL,
                    `changes` JSON NULL,
                    `created_at` TIMESTAMP NULL DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `decision_reviews_uuid_unique` (`uuid`),
                    KEY `decision_reviews_workspace_id_index` (`workspace_id`),
                    KEY `decision_reviews_decision_id_index` (`decision_id`),
                    KEY `decision_reviews_reviewer_id_index` (`reviewer_id`),
                    CONSTRAINT `decision_reviews_workspace_id_foreign` FOREIGN KEY (`workspace_id`)
                        REFERENCES `workspaces` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                    CONSTRAINT `decision_reviews_decision_id_foreign` FOREIGN KEY (`decision_id`)
                        REFERENCES `decision_records` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                    CONSTRAINT `decision_reviews_reviewer_id_foreign` FOREIGN KEY (`reviewer_id`)
                        REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }

        // 2) learning_feedback — captured signal for continuous learning (§18).
        if (! $this->hasTable($db, 'learning_feedback')) {
            $db->unprepared(
                "CREATE TABLE `learning_feedback` (
                    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `uuid` CHAR(36) NOT NULL,
                    `workspace_id` BIGINT UNSIGNED NOT NULL,
                    `interview_id` BIGINT UNSIGNED NULL,
                    `decision_id` BIGINT UNSIGNED NULL,
                    `source` VARCHAR(20) NOT NULL,
                    `rating` DECIMAL(5,2) NULL,
                    `correction` JSON NULL,
                    `notes` TEXT NULL,
                    `created_by` BIGINT UNSIGNED NULL,
                    `created_at` TIMESTAMP NULL DEFAULT NULL,
                    `updated_at` TIMESTAMP NULL DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `learning_feedback_uuid_unique` (`uuid`),
                    KEY `learning_feedback_workspace_id_index` (`workspace_id`),
                    KEY `learning_feedback_interview_id_index` (`interview_id`),
                    KEY `learning_feedback_decision_id_index` (`decision_id`),
                    KEY `learning_feedback_source_index` (`source`),
                    CONSTRAINT `learning_feedback_workspace_id_foreign` FOREIGN KEY (`workspace_id`)
                        REFERENCES `workspaces` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                    CONSTRAINT `learning_feedback_interview_id_foreign` FOREIGN KEY (`interview_id`)
                        REFERENCES `interviews` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                    CONSTRAINT `learning_feedback_decision_id_foreign` FOREIGN KEY (`decision_id`)
                        REFERENCES `decision_records` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                    CONSTRAINT `learning_feedback_created_by_foreign` FOREIGN KEY (`created_by`)
                        REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }

        // 3) Additive review state on decision_records — only if missing (guarded).
        //    Backward-compatible: existing rows keep NULL review state until reviewed.
        if ($this->hasTable($db, 'decision_records')
            && ! $this->hasColumn($db, 'decision_records', 'review_status')) {
            $db->unprepared(
                "ALTER TABLE `decision_records`
                    ADD COLUMN `review_status` VARCHAR(20) NULL AFTER `summary`,
                    ADD COLUMN `reviewed_at` TIMESTAMP NULL DEFAULT NULL AFTER `review_status`,
                    ADD COLUMN `reviewed_by` BIGINT UNSIGNED NULL AFTER `reviewed_at`"
            );
        }
    }

    public function down(Database $db): void
    {
        $db->unprepared('DROP TABLE IF EXISTS `learning_feedback`');
        $db->unprepared('DROP TABLE IF EXISTS `decision_reviews`');

        // Drop the additive columns only if they are present (mirrors the guard).
        if ($this->hasTable($db, 'decision_records')
            && $this->hasColumn($db, 'decision_records', 'review_status')) {
            $db->unprepared(
                "ALTER TABLE `decision_records`
                    DROP COLUMN `review_status`,
                    DROP COLUMN `reviewed_at`,
                    DROP COLUMN `reviewed_by`"
            );
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
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        ) > 0;
    }
};
