<?php

declare(strict_types=1);

use App\Core\Database;
use Database\Migration;

/**
 * AI Interview Engine — P5: Memory Engine + Knowledge Engine (docs/51 §1 AI
 * Orchestrator, §4 Memory Engine, §11 Knowledge Engine, §14 AI Quality Control).
 *
 * The Orchestrator runs an interview as a chat-like turn loop but WITHOUT resending
 * the whole transcript every turn. Two persistence layers make that possible:
 *
 *  - the MEMORY ENGINE (§4): one rolling `interview_memory` digest per interview
 *    (summary + extracted skills + detected contradictions + a timeline + a
 *    confidence trend + a token estimate) plus an append-only
 *    `interview_memory_items` log (each message / skill / contradiction / note /
 *    score, in `sequence` order). The Orchestrator reads a BOUNDED context — the
 *    summary as a system note + only the most recent N items — so prompt size stays
 *    capped regardless of interview length.
 *
 *  - the KNOWLEDGE ENGINE (§11): `interview_knowledge_sources` are the grounding
 *    facts the agents read (job description, company info, evaluation criteria,
 *    required skills, blueprint, scoring rules, policy, hiring workflow). They are
 *    concatenated into a trusted grounding block prepended to the system message.
 *
 * AI Quality Control (§14) is pure logic (App\Services\Interview\QualityControl) and
 * needs no schema.
 *
 * Conventions: tenant-scoped via `workspace_id` (FK → workspaces CASCADE); uuid +
 * timestamps; every FK indexed; no MySQL ENUM (code-validated VARCHAR); idempotent
 * via information_schema guards; runs outside a transaction.
 */
return new class extends Migration {
    public function up(Database $db): void
    {
        if (! $this->hasTable($db, 'interview_memory')) {
            $db->unprepared(
                "CREATE TABLE `interview_memory` (
                    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `uuid` CHAR(36) NOT NULL,
                    `workspace_id` BIGINT UNSIGNED NOT NULL,
                    `interview_id` BIGINT UNSIGNED NOT NULL,
                    `summary` TEXT NULL,
                    `skills` JSON NULL,
                    `contradictions` JSON NULL,
                    `timeline` JSON NULL,
                    `confidence_trend` JSON NULL,
                    `token_estimate` INT UNSIGNED NULL,
                    `created_at` TIMESTAMP NULL DEFAULT NULL,
                    `updated_at` TIMESTAMP NULL DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `interview_memory_uuid_unique` (`uuid`),
                    UNIQUE KEY `interview_memory_interview_id_unique` (`interview_id`),
                    KEY `interview_memory_workspace_id_index` (`workspace_id`),
                    CONSTRAINT `interview_memory_workspace_id_foreign` FOREIGN KEY (`workspace_id`)
                        REFERENCES `workspaces` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                    CONSTRAINT `interview_memory_interview_id_foreign` FOREIGN KEY (`interview_id`)
                        REFERENCES `interviews` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }

        if (! $this->hasTable($db, 'interview_memory_items')) {
            $db->unprepared(
                "CREATE TABLE `interview_memory_items` (
                    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `uuid` CHAR(36) NOT NULL,
                    `workspace_id` BIGINT UNSIGNED NOT NULL,
                    `interview_id` BIGINT UNSIGNED NOT NULL,
                    `memory_id` BIGINT UNSIGNED NULL,
                    `role` VARCHAR(20) NULL,
                    `item_type` VARCHAR(40) NOT NULL,
                    `content` TEXT NULL,
                    `meta` JSON NULL,
                    `sequence` INT NOT NULL DEFAULT 0,
                    `created_at` TIMESTAMP NULL DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `interview_memory_items_uuid_unique` (`uuid`),
                    KEY `interview_memory_items_workspace_id_index` (`workspace_id`),
                    KEY `interview_memory_items_interview_id_index` (`interview_id`),
                    KEY `interview_memory_items_memory_id_index` (`memory_id`),
                    KEY `interview_memory_items_interview_seq_index` (`interview_id`, `sequence`),
                    CONSTRAINT `interview_memory_items_workspace_id_foreign` FOREIGN KEY (`workspace_id`)
                        REFERENCES `workspaces` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                    CONSTRAINT `interview_memory_items_interview_id_foreign` FOREIGN KEY (`interview_id`)
                        REFERENCES `interviews` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                    CONSTRAINT `interview_memory_items_memory_id_foreign` FOREIGN KEY (`memory_id`)
                        REFERENCES `interview_memory` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }

        if (! $this->hasTable($db, 'interview_knowledge_sources')) {
            $db->unprepared(
                "CREATE TABLE `interview_knowledge_sources` (
                    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `uuid` CHAR(36) NOT NULL,
                    `workspace_id` BIGINT UNSIGNED NOT NULL,
                    `interview_id` BIGINT UNSIGNED NOT NULL,
                    `source_type` VARCHAR(40) NOT NULL,
                    `title` VARCHAR(150) NULL,
                    `content` TEXT NULL,
                    `meta` JSON NULL,
                    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                    `sort_order` INT NOT NULL DEFAULT 0,
                    `created_at` TIMESTAMP NULL DEFAULT NULL,
                    `updated_at` TIMESTAMP NULL DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `interview_knowledge_sources_uuid_unique` (`uuid`),
                    KEY `interview_knowledge_sources_workspace_id_index` (`workspace_id`),
                    KEY `interview_knowledge_sources_interview_id_index` (`interview_id`),
                    KEY `interview_knowledge_sources_active_index` (`interview_id`, `is_active`, `sort_order`),
                    CONSTRAINT `interview_knowledge_sources_workspace_id_foreign` FOREIGN KEY (`workspace_id`)
                        REFERENCES `workspaces` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                    CONSTRAINT `interview_knowledge_sources_interview_id_foreign` FOREIGN KEY (`interview_id`)
                        REFERENCES `interviews` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }
    }

    public function down(Database $db): void
    {
        // Reverse FK order: items reference memory, so drop them first.
        foreach (['interview_knowledge_sources', 'interview_memory_items', 'interview_memory'] as $t) {
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
