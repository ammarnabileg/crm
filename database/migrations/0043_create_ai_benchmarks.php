<?php

declare(strict_types=1);

use App\Core\Database;
use Database\Migration;

/**
 * AI Interview Engine P9 — Observability + Cost Optimizer + Model Benchmark +
 * Simulation/Sandbox (docs/51 §17 Observability/Cost, §19 Sandbox/Benchmark).
 *
 * This migration owns the persistent half of P9: the Model BENCHMARK suite. A
 * workspace defines a benchmark `scenario` (a prompt + expectations), runs it
 * across several provider/model candidates, and stores one
 * `ai_benchmark_results` row per candidate capturing accuracy, latency, cost and
 * the per-dimension quality/reasoning/language scores — so the company can
 * compare engines on its OWN data and pick the best for a capability.
 *
 * The Observability and Cost-Optimizer features (§17) read the EXISTING append
 * tables (`ai_requests`, `ai_responses`) and the `ai_models` price catalog — they
 * need no new storage — and the Simulation/Sandbox (§19) is fully in-memory, so
 * neither adds tables here.
 *
 * Tenant-scoped via `workspace_id`, uuid + timestamps, idempotent via
 * information_schema guards, inline FKs (workspaces/users CASCADE/SET NULL).
 * `ai_benchmark_results` is an append/measurement table (no `updated_at`).
 * No ENUMs — `provider`/`model_key` are plain VARCHARs matching the AI catalog.
 */
return new class extends Migration {
    public function up(Database $db): void
    {
        if (! $this->hasTable($db, 'ai_benchmarks')) {
            $db->unprepared(
                "CREATE TABLE `ai_benchmarks` (
                    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `uuid` CHAR(36) NOT NULL,
                    `workspace_id` BIGINT UNSIGNED NOT NULL,
                    `name` VARCHAR(150) NOT NULL,
                    `slug` VARCHAR(160) NOT NULL,
                    `scenario` TEXT NULL,
                    `config` JSON NULL,
                    `created_by` BIGINT UNSIGNED NULL,
                    `created_at` TIMESTAMP NULL DEFAULT NULL,
                    `updated_at` TIMESTAMP NULL DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `ai_benchmarks_uuid_unique` (`uuid`),
                    UNIQUE KEY `ai_benchmarks_workspace_slug_unique` (`workspace_id`, `slug`),
                    KEY `ai_benchmarks_workspace_id_index` (`workspace_id`),
                    KEY `ai_benchmarks_created_by_index` (`created_by`),
                    CONSTRAINT `ai_benchmarks_workspace_id_foreign` FOREIGN KEY (`workspace_id`)
                        REFERENCES `workspaces` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                    CONSTRAINT `ai_benchmarks_created_by_foreign` FOREIGN KEY (`created_by`)
                        REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }

        if (! $this->hasTable($db, 'ai_benchmark_results')) {
            $db->unprepared(
                "CREATE TABLE `ai_benchmark_results` (
                    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `uuid` CHAR(36) NOT NULL,
                    `workspace_id` BIGINT UNSIGNED NOT NULL,
                    `benchmark_id` BIGINT UNSIGNED NOT NULL,
                    `provider` VARCHAR(40) NOT NULL,
                    `model_key` VARCHAR(80) NOT NULL,
                    `accuracy` DECIMAL(5,2) NULL,
                    `latency_ms` INT UNSIGNED NULL,
                    `cost` DECIMAL(12,6) NULL,
                    `quality_score` DECIMAL(5,2) NULL,
                    `reasoning_score` DECIMAL(5,2) NULL,
                    `language_score` DECIMAL(5,2) NULL,
                    `raw` JSON NULL,
                    `created_at` TIMESTAMP NULL DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `ai_benchmark_results_uuid_unique` (`uuid`),
                    KEY `ai_benchmark_results_workspace_id_index` (`workspace_id`),
                    KEY `ai_benchmark_results_benchmark_id_index` (`benchmark_id`),
                    KEY `ai_benchmark_results_provider_index` (`provider`),
                    CONSTRAINT `ai_benchmark_results_workspace_id_foreign` FOREIGN KEY (`workspace_id`)
                        REFERENCES `workspaces` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                    CONSTRAINT `ai_benchmark_results_benchmark_id_foreign` FOREIGN KEY (`benchmark_id`)
                        REFERENCES `ai_benchmarks` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }
    }

    public function down(Database $db): void
    {
        foreach (['ai_benchmark_results', 'ai_benchmarks'] as $table) {
            $db->unprepared("DROP TABLE IF EXISTS `{$table}`");
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
