<?php

declare(strict_types=1);

use App\Core\Database;
use Database\Migration;

/**
 * AI Interview Engine — P8: Anti-Cheating Engine (docs/51 §16).
 *
 * CRITICAL DESIGN RULE — CONFIDENCE ONLY, NEVER PROOF. The signals captured here
 * (tab switches, paste events, multiple faces/voices, etc.) are ADVISORY. They are
 * aggregated into a normalized 0–100 CONFIDENCE SCORE with a low/medium/high
 * CONFIDENCE BAND — never a boolean "cheated", never a verdict, never an accusation.
 * The band (`level`) describes how much corroborating signal was observed; it is
 * explicitly NOT a finding of misconduct. A human reviewer always interprets the
 * score in context. No column, table or downstream consumer should treat any value
 * here as evidence or proof (docs/51 §16).
 *
 * Two tables:
 *   - `cheating_signals`: high-volume, append-only event log (one row per observed
 *     signal). NO updated_at — signals are immutable once recorded.
 *   - `cheating_scores`: the aggregated CONFIDENCE per interview, UNIQUE per
 *     interview_id (upserted as more signals arrive).
 *
 * Tenant-scoped via `workspace_id`; idempotent via information_schema guards; inline
 * FKs to workspaces + interviews with ON DELETE CASCADE. Self-contained: both FK
 * targets (workspaces, interviews) already exist.
 */
return new class extends Migration {
    public function up(Database $db): void
    {
        if (! $this->hasTable($db, 'cheating_signals')) {
            $db->unprepared(
                "CREATE TABLE `cheating_signals` (
                    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `uuid` CHAR(36) NOT NULL,
                    `workspace_id` BIGINT UNSIGNED NOT NULL,
                    `interview_id` BIGINT UNSIGNED NOT NULL,
                    `signal_type` VARCHAR(60) NOT NULL,
                    `weight` DECIMAL(6,3) NOT NULL DEFAULT 0,
                    `value` DECIMAL(8,3) NULL,
                    `observed_at` TIMESTAMP NULL DEFAULT NULL,
                    `meta` JSON NULL,
                    `created_at` TIMESTAMP NULL DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `cheating_signals_uuid_unique` (`uuid`),
                    KEY `cheating_signals_workspace_id_index` (`workspace_id`),
                    KEY `cheating_signals_interview_id_index` (`interview_id`),
                    KEY `cheating_signals_signal_type_index` (`signal_type`),
                    CONSTRAINT `cheating_signals_workspace_id_foreign` FOREIGN KEY (`workspace_id`)
                        REFERENCES `workspaces` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                    CONSTRAINT `cheating_signals_interview_id_foreign` FOREIGN KEY (`interview_id`)
                        REFERENCES `interviews` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }

        if (! $this->hasTable($db, 'cheating_scores')) {
            $db->unprepared(
                "CREATE TABLE `cheating_scores` (
                    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `uuid` CHAR(36) NOT NULL,
                    `workspace_id` BIGINT UNSIGNED NOT NULL,
                    `interview_id` BIGINT UNSIGNED NOT NULL,
                    `confidence` DECIMAL(5,2) NOT NULL DEFAULT 0,
                    `level` VARCHAR(20) NOT NULL DEFAULT 'low',
                    `signals_count` INT UNSIGNED NOT NULL DEFAULT 0,
                    `breakdown` JSON NULL,
                    `computed_at` TIMESTAMP NULL DEFAULT NULL,
                    `created_at` TIMESTAMP NULL DEFAULT NULL,
                    `updated_at` TIMESTAMP NULL DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `cheating_scores_uuid_unique` (`uuid`),
                    UNIQUE KEY `cheating_scores_interview_id_unique` (`interview_id`),
                    KEY `cheating_scores_workspace_id_index` (`workspace_id`),
                    CONSTRAINT `cheating_scores_workspace_id_foreign` FOREIGN KEY (`workspace_id`)
                        REFERENCES `workspaces` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                    CONSTRAINT `cheating_scores_interview_id_foreign` FOREIGN KEY (`interview_id`)
                        REFERENCES `interviews` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }
    }

    public function down(Database $db): void
    {
        foreach (['cheating_scores', 'cheating_signals'] as $t) {
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
