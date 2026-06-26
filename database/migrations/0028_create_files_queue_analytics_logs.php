<?php

declare(strict_types=1);

use App\Core\Database;
use Database\Migration;

/**
 * D10 — Files, Queue, Analytics & Logs (CREATE / structure only).
 *
 * The infrastructure domain (docs/database/11-Files-Queue-Analytics-Logs.md).
 * Owns four concerns that underpin every other domain:
 *  - File Manager:  `files`, `folders`, `storage_providers`, `file_versions`
 *  - Queue/Scheduler (no-CLI): `queued_jobs`, `failed_jobs`, `scheduled_tasks`
 *  - Analytics rollups: `daily_analytics`, `monthly_analytics`, `usage_analytics`,
 *    `hiring_analytics`, `ai_analytics`, `interview_analytics`,
 *    `performance_analytics`
 *  - Log/audit sinks: `system_logs`, `security_logs`, `api_logs`, `billing_logs`
 *
 * NOT created here: the audit trail `activity_logs` — it is already BUILT as
 * `activity_log` (migrations 0015/0016); the plural rename is a later task.
 * The shared polymorphic `attachments` table (D0) references `files.id`; that FK
 * is added by the lead, not here.
 *
 * Two-file pattern: this file owns COLUMNS + ALL INDEXES only — NO foreign key
 * constraints. Every FK (files/folders/file_versions/storage_providers → the
 * BUILT workspaces / users, intra-domain folders/files/storage_providers, and
 * lookup_values from 0018) is added in the paired FK migration
 * 0128_fk_files_queue_analytics_logs.php. Idempotent via information_schema guards.
 *
 * Scale profile (Bible §1/§4/§7): the analytics rollups and the operational logs
 * are append-mostly, very-high-volume, FK-LIGHT (integrity at the app/rollup
 * layer) and OMIT `uuid` (addressed by natural key / id+time for write
 * throughput). `queued_jobs` / `failed_jobs` are high-churn operational tables.
 * Tables marked "scale: partition candidate" are partition-READY (narrow rows +
 * the stated indexes) only — InnoDB FKs and partitions conflict, so physical
 * RANGE partitioning is applied later as an ops step, never here.
 *
 * Config-driven (no ENUM, Bible §2): `files.visibility_id`,
 * `*_analytics.metric_id` and `performance_analytics.subject_*` reference
 * `lookup_values` / are polymorphic soft refs; status/level/driver strings the
 * doc models as VARCHAR (e.g. `system_logs.level`, `storage_providers.driver`)
 * are plain VARCHAR, never the ENUM type.
 */
return new class extends Migration {
    /** System-default storage providers seeded with workspace_id = NULL (platform scope). */
    private array $storageProviders = [
        // [name, driver, is_default]
        ['Default Local', 'local', 1],
        ['Amazon S3', 's3', 0],
        ['Google Cloud Storage', 'gcs', 0],
        ['Azure Blob Storage', 'azure', 0],
    ];

    public function up(Database $db): void
    {
        // Part A — File Manager.
        $this->createStorageProviders($db);
        $this->createFolders($db);
        $this->createFiles($db);
        $this->createFileVersions($db);

        // Part B — Queue & Scheduler (no-CLI).
        $this->createQueuedJobs($db);
        $this->createFailedJobs($db);
        $this->createScheduledTasks($db);

        // Part C — Analytics rollups (FK-light, no uuid, partition-ready).
        $this->createDailyAnalytics($db);
        $this->createMonthlyAnalytics($db);
        $this->createUsageAnalytics($db);
        $this->createHiringAnalytics($db);
        $this->createAiAnalytics($db);
        $this->createInterviewAnalytics($db);
        $this->createPerformanceAnalytics($db);

        // Part D — Logs (FK-light, no uuid, partition-ready). activity_logs is
        // BUILT as activity_log and is intentionally NOT (re)created here.
        $this->createSystemLogs($db);
        $this->createSecurityLogs($db);
        $this->createApiLogs($db);
        $this->createBillingLogs($db);

        // Seed system-default storage providers (small catalog) — idempotent.
        $this->seedStorageProviders($db);
    }

    public function down(Database $db): void
    {
        // Drop children before parents (no FKs exist here, but keep a sane order).
        foreach ([
            'billing_logs',
            'api_logs',
            'security_logs',
            'system_logs',
            'performance_analytics',
            'interview_analytics',
            'ai_analytics',
            'hiring_analytics',
            'usage_analytics',
            'monthly_analytics',
            'daily_analytics',
            'scheduled_tasks',
            'failed_jobs',
            'queued_jobs',
            'file_versions',
            'files',
            'folders',
            'storage_providers',
        ] as $table) {
            $db->unprepared("DROP TABLE IF EXISTS `{$table}`");
        }
    }

    // ---------------------------------------------------------------- Part A

    private function createStorageProviders(Database $db): void
    {
        if ($this->hasTable($db, 'storage_providers')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `storage_providers` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `workspace_id` BIGINT UNSIGNED NULL,
                `name` VARCHAR(120) NOT NULL,
                `driver` VARCHAR(40) NOT NULL,
                `config` JSON NULL,
                `is_default` TINYINT(1) NOT NULL DEFAULT 0,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                `deleted_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `storage_providers_uuid_unique` (`uuid`),
                UNIQUE KEY `storage_providers_company_name_unique` (`workspace_id`, `name`),
                KEY `storage_providers_workspace_default_index` (`workspace_id`, `is_default`),
                KEY `storage_providers_driver_index` (`driver`),
                KEY `storage_providers_deleted_at_index` (`deleted_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    private function createFolders(Database $db): void
    {
        if ($this->hasTable($db, 'folders')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `folders` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `workspace_id` BIGINT UNSIGNED NOT NULL,
                `parent_id` BIGINT UNSIGNED NULL,
                `name` VARCHAR(255) NOT NULL,
                `path` VARCHAR(1024) NULL,
                `created_by` BIGINT UNSIGNED NULL,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                `deleted_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `folders_uuid_unique` (`uuid`),
                UNIQUE KEY `folders_workspace_parent_name_unique` (`workspace_id`, `parent_id`, `name`),
                KEY `folders_workspace_parent_index` (`workspace_id`, `parent_id`),
                KEY `folders_created_by_index` (`created_by`),
                KEY `folders_deleted_at_index` (`deleted_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    private function createFiles(Database $db): void
    {
        if ($this->hasTable($db, 'files')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `files` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `workspace_id` BIGINT UNSIGNED NOT NULL,
                `user_id` BIGINT UNSIGNED NULL,
                `folder_id` BIGINT UNSIGNED NULL,
                `storage_provider_id` BIGINT UNSIGNED NOT NULL,
                `disk` VARCHAR(40) NOT NULL DEFAULT 'local',
                `path` VARCHAR(512) NOT NULL,
                `original_name` VARCHAR(255) NOT NULL,
                `mime` VARCHAR(150) NOT NULL,
                `size` BIGINT UNSIGNED NOT NULL DEFAULT 0,
                `checksum` CHAR(64) NULL,
                `visibility_id` BIGINT UNSIGNED NOT NULL,
                `meta` JSON NULL,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                `deleted_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `files_uuid_unique` (`uuid`),
                UNIQUE KEY `files_workspace_path_unique` (`workspace_id`, `disk`, `path`),
                KEY `files_workspace_folder_index` (`workspace_id`, `folder_id`),
                KEY `files_workspace_user_index` (`workspace_id`, `user_id`),
                KEY `files_workspace_created_index` (`workspace_id`, `created_at`),
                KEY `files_storage_provider_index` (`storage_provider_id`),
                KEY `files_visibility_index` (`visibility_id`),
                KEY `files_checksum_index` (`checksum`),
                KEY `files_deleted_at_index` (`deleted_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    private function createFileVersions(Database $db): void
    {
        if ($this->hasTable($db, 'file_versions')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `file_versions` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `file_id` BIGINT UNSIGNED NOT NULL,
                `workspace_id` BIGINT UNSIGNED NOT NULL,
                `version` INT UNSIGNED NOT NULL DEFAULT 1,
                `disk` VARCHAR(40) NOT NULL DEFAULT 'local',
                `path` VARCHAR(512) NOT NULL,
                `size` BIGINT UNSIGNED NOT NULL DEFAULT 0,
                `checksum` CHAR(64) NULL,
                `created_by` BIGINT UNSIGNED NULL,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `file_versions_uuid_unique` (`uuid`),
                UNIQUE KEY `file_versions_file_version_unique` (`file_id`, `version`),
                KEY `file_versions_workspace_index` (`workspace_id`),
                KEY `file_versions_created_by_index` (`created_by`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    // ---------------------------------------------------------------- Part B

    private function createQueuedJobs(Database $db): void
    {
        if ($this->hasTable($db, 'queued_jobs')) {
            return;
        }
        // FK-light: high-churn operational queue; workspace_id is an indexed soft
        // reference only (rows are hard-deleted on success).
        $db->unprepared(
            "CREATE TABLE `queued_jobs` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `workspace_id` BIGINT UNSIGNED NULL,
                `queue` VARCHAR(120) NOT NULL DEFAULT 'default',
                `payload` LONGTEXT NOT NULL,
                `attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
                `reserved_at` INT UNSIGNED NULL,
                `available_at` INT UNSIGNED NOT NULL,
                `created_at` INT UNSIGNED NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `queued_jobs_uuid_unique` (`uuid`),
                KEY `queued_jobs_reserve_index` (`queue`, `reserved_at`, `available_at`),
                KEY `queued_jobs_workspace_index` (`workspace_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    private function createFailedJobs(Database $db): void
    {
        if ($this->hasTable($db, 'failed_jobs')) {
            return;
        }
        // FK-light: dead-letter store; correlated to queued_jobs by uuid (logical).
        $db->unprepared(
            "CREATE TABLE `failed_jobs` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `workspace_id` BIGINT UNSIGNED NULL,
                `queue` VARCHAR(120) NOT NULL DEFAULT 'default',
                `payload` LONGTEXT NOT NULL,
                `exception` LONGTEXT NOT NULL,
                `failed_at` TIMESTAMP NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`),
                KEY `failed_jobs_failed_at_index` (`failed_at`),
                KEY `failed_jobs_workspace_index` (`workspace_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    private function createScheduledTasks(Database $db): void
    {
        if ($this->hasTable($db, 'scheduled_tasks')) {
            return;
        }
        // Small platform-config registry (no FKs). Status string is VARCHAR, not ENUM.
        $db->unprepared(
            "CREATE TABLE `scheduled_tasks` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `name` VARCHAR(150) NOT NULL,
                `description` VARCHAR(255) NULL,
                `command` VARCHAR(255) NULL,
                `cron` VARCHAR(120) NOT NULL,
                `timezone` VARCHAR(64) NULL,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `last_run_at` TIMESTAMP NULL DEFAULT NULL,
                `next_run_at` TIMESTAMP NULL DEFAULT NULL,
                `last_status` VARCHAR(20) NULL,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `scheduled_tasks_uuid_unique` (`uuid`),
                UNIQUE KEY `scheduled_tasks_name_unique` (`name`),
                KEY `scheduled_tasks_due_index` (`is_active`, `next_run_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    // ---------------------------------------------------------------- Part C
    // All analytics: FK-light (workspace_id / dimensions = indexed soft refs),
    // no uuid, no deleted_at (Bible §1 high-volume exception). UNIQUE upsert key
    // per (workspace, period, [dimension, metric]) supports INSERT ... ON
    // DUPLICATE KEY UPDATE.

    private function createDailyAnalytics(Database $db): void
    {
        if ($this->hasTable($db, 'daily_analytics')) {
            return;
        }
        // scale: partition candidate by RANGE(date), monthly.
        $db->unprepared(
            "CREATE TABLE `daily_analytics` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `workspace_id` BIGINT UNSIGNED NOT NULL,
                `date` DATE NOT NULL,
                `metric_id` BIGINT UNSIGNED NOT NULL,
                `value` DECIMAL(20,4) NOT NULL DEFAULT 0,
                `count` BIGINT UNSIGNED NULL,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `daily_workspace_date_metric_unique` (`workspace_id`, `date`, `metric_id`),
                KEY `daily_workspace_date_index` (`workspace_id`, `date`),
                KEY `daily_metric_index` (`metric_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    private function createMonthlyAnalytics(Database $db): void
    {
        if ($this->hasTable($db, 'monthly_analytics')) {
            return;
        }
        // scale: partition candidate by RANGE(year), yearly.
        $db->unprepared(
            "CREATE TABLE `monthly_analytics` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `workspace_id` BIGINT UNSIGNED NOT NULL,
                `year` SMALLINT UNSIGNED NOT NULL,
                `month` TINYINT UNSIGNED NOT NULL,
                `metric_id` BIGINT UNSIGNED NOT NULL,
                `value` DECIMAL(20,4) NOT NULL DEFAULT 0,
                `count` BIGINT UNSIGNED NULL,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `monthly_workspace_period_metric_unique` (`workspace_id`, `year`, `month`, `metric_id`),
                KEY `monthly_workspace_period_index` (`workspace_id`, `year`, `month`),
                KEY `monthly_metric_index` (`metric_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    private function createUsageAnalytics(Database $db): void
    {
        if ($this->hasTable($db, 'usage_analytics')) {
            return;
        }
        // scale: partition candidate by RANGE(period_date), monthly.
        $db->unprepared(
            "CREATE TABLE `usage_analytics` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `workspace_id` BIGINT UNSIGNED NOT NULL,
                `period_date` DATE NOT NULL,
                `period_type` VARCHAR(10) NOT NULL DEFAULT 'day',
                `metric_id` BIGINT UNSIGNED NOT NULL,
                `value` DECIMAL(20,4) NOT NULL DEFAULT 0,
                `limit_value` DECIMAL(20,4) NULL,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `usage_workspace_period_metric_unique` (`workspace_id`, `period_date`, `period_type`, `metric_id`),
                KEY `usage_workspace_period_index` (`workspace_id`, `period_date`),
                KEY `usage_metric_index` (`metric_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    private function createHiringAnalytics(Database $db): void
    {
        if ($this->hasTable($db, 'hiring_analytics')) {
            return;
        }
        // scale: partition candidate by RANGE(period_date), monthly.
        $db->unprepared(
            "CREATE TABLE `hiring_analytics` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `workspace_id` BIGINT UNSIGNED NOT NULL,
                `period_date` DATE NOT NULL,
                `job_id` BIGINT UNSIGNED NULL,
                `applications_count` INT UNSIGNED NOT NULL DEFAULT 0,
                `screened_count` INT UNSIGNED NOT NULL DEFAULT 0,
                `interviews_count` INT UNSIGNED NOT NULL DEFAULT 0,
                `offers_count` INT UNSIGNED NOT NULL DEFAULT 0,
                `hires_count` INT UNSIGNED NOT NULL DEFAULT 0,
                `rejections_count` INT UNSIGNED NOT NULL DEFAULT 0,
                `avg_time_to_hire_days` DECIMAL(8,2) NULL,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `hiring_workspace_period_job_unique` (`workspace_id`, `period_date`, `job_id`),
                KEY `hiring_workspace_period_index` (`workspace_id`, `period_date`),
                KEY `hiring_job_index` (`job_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    private function createAiAnalytics(Database $db): void
    {
        if ($this->hasTable($db, 'ai_analytics')) {
            return;
        }
        // scale: partition candidate by RANGE(period_date), monthly.
        $db->unprepared(
            "CREATE TABLE `ai_analytics` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `workspace_id` BIGINT UNSIGNED NOT NULL,
                `period_date` DATE NOT NULL,
                `provider_id` BIGINT UNSIGNED NULL,
                `model_id` BIGINT UNSIGNED NULL,
                `requests_count` BIGINT UNSIGNED NOT NULL DEFAULT 0,
                `tokens_input` BIGINT UNSIGNED NOT NULL DEFAULT 0,
                `tokens_output` BIGINT UNSIGNED NOT NULL DEFAULT 0,
                `total_cost` DECIMAL(16,6) NOT NULL DEFAULT 0,
                `errors_count` BIGINT UNSIGNED NOT NULL DEFAULT 0,
                `avg_latency_ms` DECIMAL(10,2) NULL,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `ai_workspace_period_dim_unique` (`workspace_id`, `period_date`, `provider_id`, `model_id`),
                KEY `ai_workspace_period_index` (`workspace_id`, `period_date`),
                KEY `ai_provider_model_index` (`provider_id`, `model_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    private function createInterviewAnalytics(Database $db): void
    {
        if ($this->hasTable($db, 'interview_analytics')) {
            return;
        }
        // scale: partition candidate by RANGE(period_date), monthly.
        $db->unprepared(
            "CREATE TABLE `interview_analytics` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `workspace_id` BIGINT UNSIGNED NOT NULL,
                `period_date` DATE NOT NULL,
                `job_id` BIGINT UNSIGNED NULL,
                `interviews_count` INT UNSIGNED NOT NULL DEFAULT 0,
                `completed_count` INT UNSIGNED NOT NULL DEFAULT 0,
                `no_show_count` INT UNSIGNED NOT NULL DEFAULT 0,
                `avg_score` DECIMAL(6,2) NULL,
                `avg_duration_min` DECIMAL(8,2) NULL,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `interview_workspace_period_job_unique` (`workspace_id`, `period_date`, `job_id`),
                KEY `interview_workspace_period_index` (`workspace_id`, `period_date`),
                KEY `interview_job_index` (`job_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    private function createPerformanceAnalytics(Database $db): void
    {
        if ($this->hasTable($db, 'performance_analytics')) {
            return;
        }
        // scale: partition candidate by RANGE(period_date), monthly.
        // subject_type/subject_id is a polymorphic soft dimension (no FK).
        $db->unprepared(
            "CREATE TABLE `performance_analytics` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `workspace_id` BIGINT UNSIGNED NOT NULL,
                `period_date` DATE NOT NULL,
                `subject_type` VARCHAR(60) NOT NULL,
                `subject_id` BIGINT UNSIGNED NULL,
                `metric_id` BIGINT UNSIGNED NULL,
                `score_value` DECIMAL(12,4) NOT NULL DEFAULT 0,
                `count` BIGINT UNSIGNED NULL,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `perf_workspace_period_subject_metric_unique` (`workspace_id`, `period_date`, `subject_type`, `subject_id`, `metric_id`),
                KEY `perf_workspace_period_index` (`workspace_id`, `period_date`),
                KEY `perf_subject_index` (`subject_type`, `subject_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    // ---------------------------------------------------------------- Part D
    // Logs: append-only, immutable, very-high-volume. No uuid, no updated_at, no
    // deleted_at. FK-light (workspace_id/user_id = indexed soft refs; subjects
    // polymorphic). activity_logs is BUILT as activity_log and excluded here.

    private function createSystemLogs(Database $db): void
    {
        if ($this->hasTable($db, 'system_logs')) {
            return;
        }
        // scale: partition candidate by RANGE(created_at), monthly.
        // level/channel are VARCHAR strings, not ENUM (Bible §2).
        $db->unprepared(
            "CREATE TABLE `system_logs` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `workspace_id` BIGINT UNSIGNED NULL,
                `level` VARCHAR(20) NOT NULL,
                `channel` VARCHAR(60) NULL,
                `message` VARCHAR(255) NOT NULL,
                `context` JSON NULL,
                `correlation_id` CHAR(36) NULL,
                `created_at` TIMESTAMP NOT NULL,
                PRIMARY KEY (`id`),
                KEY `system_logs_created_index` (`created_at`),
                KEY `system_logs_level_created_index` (`level`, `created_at`),
                KEY `system_logs_workspace_created_index` (`workspace_id`, `created_at`),
                KEY `system_logs_correlation_index` (`correlation_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    private function createSecurityLogs(Database $db): void
    {
        if ($this->hasTable($db, 'security_logs')) {
            return;
        }
        // scale: partition candidate by RANGE(created_at), monthly.
        $db->unprepared(
            "CREATE TABLE `security_logs` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `workspace_id` BIGINT UNSIGNED NULL,
                `user_id` BIGINT UNSIGNED NULL,
                `event` VARCHAR(80) NOT NULL,
                `severity` VARCHAR(20) NULL,
                `ip` VARCHAR(45) NULL,
                `user_agent` VARCHAR(255) NULL,
                `context` JSON NULL,
                `created_at` TIMESTAMP NOT NULL,
                PRIMARY KEY (`id`),
                KEY `security_logs_created_index` (`created_at`),
                KEY `security_logs_event_created_index` (`event`, `created_at`),
                KEY `security_logs_workspace_created_index` (`workspace_id`, `created_at`),
                KEY `security_logs_ip_index` (`ip`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    private function createApiLogs(Database $db): void
    {
        if ($this->hasTable($db, 'api_logs')) {
            return;
        }
        // scale: partition candidate by RANGE(created_at), monthly (highest volume).
        $db->unprepared(
            "CREATE TABLE `api_logs` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `workspace_id` BIGINT UNSIGNED NULL,
                `token_id` BIGINT UNSIGNED NULL,
                `user_id` BIGINT UNSIGNED NULL,
                `method` VARCHAR(10) NOT NULL,
                `path` VARCHAR(255) NOT NULL,
                `status` SMALLINT UNSIGNED NOT NULL,
                `duration_ms` INT UNSIGNED NULL,
                `ip` VARCHAR(45) NULL,
                `request_id` CHAR(36) NULL,
                `meta` JSON NULL,
                `created_at` TIMESTAMP NOT NULL,
                PRIMARY KEY (`id`),
                KEY `api_logs_created_index` (`created_at`),
                KEY `api_logs_workspace_created_index` (`workspace_id`, `created_at`),
                KEY `api_logs_token_created_index` (`token_id`, `created_at`),
                KEY `api_logs_status_created_index` (`status`, `created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    private function createBillingLogs(Database $db): void
    {
        if ($this->hasTable($db, 'billing_logs')) {
            return;
        }
        // scale: partition candidate by RANGE(created_at), monthly.
        // subject_type/subject_id polymorphic soft ref; never stores card/PAN data.
        $db->unprepared(
            "CREATE TABLE `billing_logs` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `workspace_id` BIGINT UNSIGNED NOT NULL,
                `user_id` BIGINT UNSIGNED NULL,
                `event` VARCHAR(80) NOT NULL,
                `subject_type` VARCHAR(120) NULL,
                `subject_id` BIGINT UNSIGNED NULL,
                `amount` DECIMAL(16,4) NULL,
                `currency_id` BIGINT UNSIGNED NULL,
                `gateway` VARCHAR(60) NULL,
                `context` JSON NULL,
                `created_at` TIMESTAMP NOT NULL,
                PRIMARY KEY (`id`),
                KEY `billing_logs_workspace_created_index` (`workspace_id`, `created_at`),
                KEY `billing_logs_event_created_index` (`event`, `created_at`),
                KEY `billing_logs_subject_index` (`subject_type`, `subject_id`),
                KEY `billing_logs_created_index` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    // ----------------------------------------------------------------- Seeds

    /**
     * Seed the system-default storage providers (workspace_id = NULL = platform
     * scope) the doc lists (local/s3/gcs/azure). The local provider is the
     * is_default for shared-hosting buyers with no object-store account. Idempotent.
     * Note: storage_providers has no `is_system` column, so scope is expressed by
     * the NULL workspace_id alone (per the doc).
     */
    private function seedStorageProviders(Database $db): void
    {
        if (! $this->hasTable($db, 'storage_providers')) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        foreach ($this->storageProviders as [$name, $driver, $isDefault]) {
            $exists = $db->table('storage_providers')
                ->whereNull('workspace_id')
                ->where('name', '=', $name)
                ->exists();
            if ($exists) {
                continue;
            }
            $db->table('storage_providers')->insert([
                'uuid'         => $this->uuid($db),
                'workspace_id' => null,
                'name'         => $name,
                'driver'       => $driver,
                'config'       => null,
                'is_default'   => $isDefault,
                'is_active'    => 1,
                'created_at'   => $now,
                'updated_at'   => $now,
            ]);
        }
    }

    // --------------------------------------------------------------- Helpers

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

    private function hasColumn(Database $db, string $table, string $column): bool
    {
        return (int) $db->scalar(
            'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        ) > 0;
    }

    private function hasConstraint(Database $db, string $table, string $constraint): bool
    {
        return (int) $db->scalar(
            'SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?',
            [$table, $constraint]
        ) > 0;
    }

    private function hasIndex(Database $db, string $table, string $index): bool
    {
        return (int) $db->scalar(
            'SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
            [$table, $index]
        ) > 0;
    }
};
