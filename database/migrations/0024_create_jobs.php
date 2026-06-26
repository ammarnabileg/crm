<?php

declare(strict_types=1);

use App\Core\Database;
use Database\Migration;

/**
 * D5 — Jobs (structure only; foreign keys live in 0124_fk_jobs.php).
 *
 * Creates the twelve tables of the recruitment anchor domain (docs/database/06):
 *  - `jobs`            — the job posting / requisition (anchor of the domain).
 *  - `job_statuses`    — config-driven status catalog + workflow flags
 *                        (draft/open/paused/closed/archived seeded as system rows).
 *  - `locations`       — reusable workspace offices / job sites.
 *  - `job_locations`   — pivot: which locations a job is posted at.
 *  - `job_skills`      — pivot: skills a job requires (skill catalog owned by D6).
 *  - `job_languages`   — pivot: languages a job requires (catalog is D0).
 *  - `benefits`        — tenant-curated benefits/perks catalog.
 *  - `job_benefits`    — pivot: benefits attached to a job.
 *  - `job_questions`   — screening / application-form question definitions.
 *  - `job_criteria`    — weighted scoring criteria for AI + human evaluation.
 *  - `pipelines`       — configurable hiring workflow (template or job instance).
 *  - `pipeline_stages` — ordered stages of a pipeline.
 *
 * TWO-FILE pattern: this file declares columns + ALL indexes (PK, uuid unique,
 * business uniques, an index on every FK column, composites, FULLTEXT) but NO
 * FOREIGN KEY constraints — every FK (anchors, intra-domain, cross-domain, and
 * the jobs<->pipelines soft cycle) is added in 0124_fk_jobs.php. After the
 * CREATE TABLEs it seeds the canonical system-default `job_statuses` rows
 * (workspace_id NULL, is_system=1). Idempotent via information_schema guards.
 */
return new class extends Migration {
    /**
     * Canonical system job statuses (workspace_id NULL, is_system=1).
     * [key, label, color, sort, is_default, is_initial, is_terminal, is_published_state]
     */
    private array $jobStatuses = [
        ['draft',    'Draft',    '#9CA3AF', 1, 1, 1, 0, 0],
        ['open',     'Open',     '#16A34A', 2, 0, 1, 0, 1],
        ['paused',   'Paused',   '#F59E0B', 3, 0, 0, 0, 0],
        ['closed',   'Closed',   '#DC2626', 4, 0, 0, 1, 0],
        ['archived', 'Archived', '#6B7280', 5, 0, 0, 1, 0],
    ];

    public function up(Database $db): void
    {
        $this->createJobStatuses($db);
        $this->createPipelines($db);
        $this->createJobs($db);
        $this->createLocations($db);
        $this->createJobLocations($db);
        $this->createJobSkills($db);
        $this->createJobLanguages($db);
        $this->createBenefits($db);
        $this->createJobBenefits($db);
        $this->createJobQuestions($db);
        $this->createJobCriteria($db);
        $this->createPipelineStages($db);

        $this->seedJobStatuses($db);
    }

    public function down(Database $db): void
    {
        // Drop children before parents (FKs are dropped by 0124 down(); this
        // is defensive — DROP TABLE IF EXISTS is safe regardless of order).
        foreach ([
            'pipeline_stages',
            'job_criteria',
            'job_questions',
            'job_benefits',
            'job_languages',
            'job_skills',
            'job_locations',
            'benefits',
            'locations',
            'jobs',
            'pipelines',
            'job_statuses',
        ] as $table) {
            $db->unprepared("DROP TABLE IF EXISTS `{$table}`");
        }
    }

    private function createJobStatuses(Database $db): void
    {
        if ($this->hasTable($db, 'job_statuses')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `job_statuses` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `workspace_id` BIGINT UNSIGNED NULL,
                `key` VARCHAR(60) NOT NULL,
                `label` VARCHAR(120) NOT NULL,
                `color` VARCHAR(20) NULL,
                `sort_order` INT NOT NULL DEFAULT 0,
                `is_default` TINYINT(1) NOT NULL DEFAULT 0,
                `is_initial` TINYINT(1) NOT NULL DEFAULT 0,
                `is_terminal` TINYINT(1) NOT NULL DEFAULT 0,
                `is_published_state` TINYINT(1) NOT NULL DEFAULT 0,
                `is_system` TINYINT(1) NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                `deleted_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `job_statuses_uuid_unique` (`uuid`),
                UNIQUE KEY `job_statuses_workspace_key_unique` (`workspace_id`, `key`),
                KEY `job_statuses_workspace_sort_index` (`workspace_id`, `sort_order`),
                KEY `job_statuses_is_default_index` (`is_default`),
                KEY `job_statuses_deleted_at_index` (`deleted_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    private function createPipelines(Database $db): void
    {
        if ($this->hasTable($db, 'pipelines')) {
            return;
        }
        // NOTE: created with NO FKs (the jobs<->pipelines soft cycle is resolved
        // entirely in 0124_fk_jobs.php).
        $db->unprepared(
            "CREATE TABLE `pipelines` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `workspace_id` BIGINT UNSIGNED NOT NULL,
                `job_id` BIGINT UNSIGNED NULL,
                `name` VARCHAR(150) NOT NULL,
                `description` VARCHAR(500) NULL,
                `is_default` TINYINT(1) NOT NULL DEFAULT 0,
                `is_template` TINYINT(1) NOT NULL DEFAULT 0,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `created_by` BIGINT UNSIGNED NULL,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                `deleted_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `pipelines_uuid_unique` (`uuid`),
                UNIQUE KEY `pipelines_workspace_job_unique` (`workspace_id`, `job_id`),
                KEY `pipelines_workspace_default_index` (`workspace_id`, `is_default`),
                KEY `pipelines_job_id_index` (`job_id`),
                KEY `pipelines_created_by_index` (`created_by`),
                KEY `pipelines_deleted_at_index` (`deleted_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    private function createJobs(Database $db): void
    {
        if ($this->hasTable($db, 'jobs')) {
            return;
        }
        // NOTE: created with NO FKs (jobs<->pipelines soft cycle + all other FKs
        // are added in 0124_fk_jobs.php).
        $db->unprepared(
            "CREATE TABLE `jobs` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `workspace_id` BIGINT UNSIGNED NOT NULL,
                `job_status_id` BIGINT UNSIGNED NOT NULL,
                `pipeline_id` BIGINT UNSIGNED NULL,
                `department_id` BIGINT UNSIGNED NULL,
                `employment_type_id` BIGINT UNSIGNED NULL,
                `experience_level_id` BIGINT UNSIGNED NULL,
                `title` VARCHAR(160) NOT NULL,
                `slug` VARCHAR(180) NOT NULL,
                `description` LONGTEXT NULL,
                `summary` VARCHAR(500) NULL,
                `openings` INT UNSIGNED NOT NULL DEFAULT 1,
                `is_remote` TINYINT(1) NOT NULL DEFAULT 0,
                `salary_min` DECIMAL(12,2) NULL,
                `salary_max` DECIMAL(12,2) NULL,
                `currency_id` BIGINT UNSIGNED NULL,
                `salary_period_id` BIGINT UNSIGNED NULL,
                `is_salary_public` TINYINT(1) NOT NULL DEFAULT 0,
                `meta` JSON NULL,
                `created_by` BIGINT UNSIGNED NULL,
                `updated_by` BIGINT UNSIGNED NULL,
                `published_at` TIMESTAMP NULL DEFAULT NULL,
                `closed_at` TIMESTAMP NULL DEFAULT NULL,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                `deleted_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `jobs_uuid_unique` (`uuid`),
                UNIQUE KEY `jobs_workspace_slug_unique` (`workspace_id`, `slug`),
                KEY `jobs_workspace_status_index` (`workspace_id`, `job_status_id`),
                KEY `jobs_workspace_created_index` (`workspace_id`, `created_at`),
                KEY `jobs_workspace_dept_index` (`workspace_id`, `department_id`),
                KEY `jobs_pipeline_id_index` (`pipeline_id`),
                KEY `jobs_department_id_index` (`department_id`),
                KEY `jobs_employment_type_id_index` (`employment_type_id`),
                KEY `jobs_experience_level_id_index` (`experience_level_id`),
                KEY `jobs_currency_id_index` (`currency_id`),
                KEY `jobs_salary_period_id_index` (`salary_period_id`),
                KEY `jobs_created_by_index` (`created_by`),
                KEY `jobs_updated_by_index` (`updated_by`),
                KEY `jobs_published_at_index` (`published_at`),
                KEY `jobs_deleted_at_index` (`deleted_at`),
                FULLTEXT KEY `jobs_title_description_fulltext` (`title`, `description`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    private function createLocations(Database $db): void
    {
        if ($this->hasTable($db, 'locations')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `locations` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `workspace_id` BIGINT UNSIGNED NOT NULL,
                `name` VARCHAR(150) NULL,
                `country_id` BIGINT UNSIGNED NOT NULL,
                `city` VARCHAR(120) NULL,
                `state` VARCHAR(120) NULL,
                `address_line` VARCHAR(255) NULL,
                `postal_code` VARCHAR(20) NULL,
                `timezone_id` BIGINT UNSIGNED NULL,
                `latitude` DECIMAL(10,7) NULL,
                `longitude` DECIMAL(10,7) NULL,
                `is_remote` TINYINT(1) NOT NULL DEFAULT 0,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                `deleted_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `locations_uuid_unique` (`uuid`),
                KEY `locations_workspace_id_index` (`workspace_id`),
                KEY `locations_workspace_country_index` (`workspace_id`, `country_id`),
                KEY `locations_country_id_index` (`country_id`),
                KEY `locations_timezone_id_index` (`timezone_id`),
                KEY `locations_lat_lng_index` (`latitude`, `longitude`),
                KEY `locations_deleted_at_index` (`deleted_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    private function createJobLocations(Database $db): void
    {
        if ($this->hasTable($db, 'job_locations')) {
            return;
        }
        // Pure pivot — no uuid (never addressed externally), no soft-delete.
        $db->unprepared(
            "CREATE TABLE `job_locations` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `workspace_id` BIGINT UNSIGNED NOT NULL,
                `job_id` BIGINT UNSIGNED NOT NULL,
                `location_id` BIGINT UNSIGNED NOT NULL,
                `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `job_locations_job_location_unique` (`job_id`, `location_id`),
                KEY `job_locations_location_id_index` (`location_id`),
                KEY `job_locations_workspace_id_index` (`workspace_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    private function createJobSkills(Database $db): void
    {
        if ($this->hasTable($db, 'job_skills')) {
            return;
        }
        // Pure pivot — no uuid, no soft-delete. skill_id -> D6 `skills` (FK in 0124).
        $db->unprepared(
            "CREATE TABLE `job_skills` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `workspace_id` BIGINT UNSIGNED NOT NULL,
                `job_id` BIGINT UNSIGNED NOT NULL,
                `skill_id` BIGINT UNSIGNED NOT NULL,
                `required_level_id` BIGINT UNSIGNED NULL,
                `is_required` TINYINT(1) NOT NULL DEFAULT 1,
                `weight` DECIMAL(5,2) NOT NULL DEFAULT 1.00,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `job_skills_job_skill_unique` (`job_id`, `skill_id`),
                KEY `job_skills_skill_id_index` (`skill_id`),
                KEY `job_skills_required_level_id_index` (`required_level_id`),
                KEY `job_skills_workspace_id_index` (`workspace_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    private function createJobLanguages(Database $db): void
    {
        if ($this->hasTable($db, 'job_languages')) {
            return;
        }
        // Pure pivot — no uuid, no soft-delete. language_id -> D0 `languages` (FK in 0124).
        $db->unprepared(
            "CREATE TABLE `job_languages` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `workspace_id` BIGINT UNSIGNED NOT NULL,
                `job_id` BIGINT UNSIGNED NOT NULL,
                `language_id` BIGINT UNSIGNED NOT NULL,
                `proficiency_id` BIGINT UNSIGNED NULL,
                `is_required` TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `job_languages_job_language_unique` (`job_id`, `language_id`),
                KEY `job_languages_language_id_index` (`language_id`),
                KEY `job_languages_proficiency_id_index` (`proficiency_id`),
                KEY `job_languages_workspace_id_index` (`workspace_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    private function createBenefits(Database $db): void
    {
        if ($this->hasTable($db, 'benefits')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `benefits` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `workspace_id` BIGINT UNSIGNED NOT NULL,
                `key` VARCHAR(60) NOT NULL,
                `label` VARCHAR(150) NOT NULL,
                `description` VARCHAR(500) NULL,
                `category_id` BIGINT UNSIGNED NULL,
                `icon` VARCHAR(60) NULL,
                `sort_order` INT NOT NULL DEFAULT 0,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                `deleted_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `benefits_uuid_unique` (`uuid`),
                UNIQUE KEY `benefits_workspace_key_unique` (`workspace_id`, `key`),
                KEY `benefits_category_id_index` (`category_id`),
                KEY `benefits_workspace_active_index` (`workspace_id`, `is_active`),
                KEY `benefits_deleted_at_index` (`deleted_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    private function createJobBenefits(Database $db): void
    {
        if ($this->hasTable($db, 'job_benefits')) {
            return;
        }
        // Pure pivot — no uuid, no soft-delete.
        $db->unprepared(
            "CREATE TABLE `job_benefits` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `workspace_id` BIGINT UNSIGNED NOT NULL,
                `job_id` BIGINT UNSIGNED NOT NULL,
                `benefit_id` BIGINT UNSIGNED NOT NULL,
                `value` VARCHAR(255) NULL,
                `sort_order` INT NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `job_benefits_job_benefit_unique` (`job_id`, `benefit_id`),
                KEY `job_benefits_benefit_id_index` (`benefit_id`),
                KEY `job_benefits_workspace_id_index` (`workspace_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    private function createJobQuestions(Database $db): void
    {
        if ($this->hasTable($db, 'job_questions')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `job_questions` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `workspace_id` BIGINT UNSIGNED NOT NULL,
                `job_id` BIGINT UNSIGNED NOT NULL,
                `question_type_id` BIGINT UNSIGNED NOT NULL,
                `question` VARCHAR(500) NOT NULL,
                `help_text` VARCHAR(255) NULL,
                `options` JSON NULL,
                `is_required` TINYINT(1) NOT NULL DEFAULT 0,
                `is_knockout` TINYINT(1) NOT NULL DEFAULT 0,
                `knockout_answer` JSON NULL,
                `sort_order` INT NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                `deleted_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `job_questions_uuid_unique` (`uuid`),
                KEY `job_questions_job_sort_index` (`job_id`, `sort_order`),
                KEY `job_questions_question_type_id_index` (`question_type_id`),
                KEY `job_questions_workspace_id_index` (`workspace_id`),
                KEY `job_questions_deleted_at_index` (`deleted_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    private function createJobCriteria(Database $db): void
    {
        if ($this->hasTable($db, 'job_criteria')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `job_criteria` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `workspace_id` BIGINT UNSIGNED NOT NULL,
                `job_id` BIGINT UNSIGNED NOT NULL,
                `criterion_type_id` BIGINT UNSIGNED NULL,
                `name` VARCHAR(150) NOT NULL,
                `description` VARCHAR(500) NULL,
                `weight` DECIMAL(5,2) NOT NULL DEFAULT 1.00,
                `max_score` DECIMAL(5,2) NOT NULL DEFAULT 10.00,
                `is_ai_scored` TINYINT(1) NOT NULL DEFAULT 1,
                `sort_order` INT NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                `deleted_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `job_criteria_uuid_unique` (`uuid`),
                KEY `job_criteria_job_sort_index` (`job_id`, `sort_order`),
                KEY `job_criteria_criterion_type_id_index` (`criterion_type_id`),
                KEY `job_criteria_workspace_id_index` (`workspace_id`),
                KEY `job_criteria_deleted_at_index` (`deleted_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    private function createPipelineStages(Database $db): void
    {
        if ($this->hasTable($db, 'pipeline_stages')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `pipeline_stages` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `workspace_id` BIGINT UNSIGNED NOT NULL,
                `pipeline_id` BIGINT UNSIGNED NOT NULL,
                `application_status_id` BIGINT UNSIGNED NULL,
                `stage_type_id` BIGINT UNSIGNED NOT NULL,
                `name` VARCHAR(150) NOT NULL,
                `description` VARCHAR(500) NULL,
                `color` VARCHAR(20) NULL,
                `sort_order` INT NOT NULL DEFAULT 0,
                `is_initial` TINYINT(1) NOT NULL DEFAULT 0,
                `is_terminal` TINYINT(1) NOT NULL DEFAULT 0,
                `is_passed` TINYINT(1) NOT NULL DEFAULT 0,
                `auto_advance` TINYINT(1) NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                `deleted_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `pipeline_stages_uuid_unique` (`uuid`),
                UNIQUE KEY `pipeline_stages_pipeline_sort_unique` (`pipeline_id`, `sort_order`),
                KEY `pipeline_stages_application_status_id_index` (`application_status_id`),
                KEY `pipeline_stages_stage_type_id_index` (`stage_type_id`),
                KEY `pipeline_stages_workspace_id_index` (`workspace_id`),
                KEY `pipeline_stages_deleted_at_index` (`deleted_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    /**
     * Seed the canonical system job lifecycle (workspace_id NULL, is_system=1).
     * Idempotent: each row is keyed by (workspace_id IS NULL, key).
     */
    private function seedJobStatuses(Database $db): void
    {
        if (! $this->hasTable($db, 'job_statuses')) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        foreach ($this->jobStatuses as [$key, $label, $color, $sort, $isDefault, $isInitial, $isTerminal, $isPublished]) {
            $exists = (int) $db->scalar(
                'SELECT COUNT(*) FROM `job_statuses` WHERE `workspace_id` IS NULL AND `key` = ?',
                [$key]
            ) > 0;
            if ($exists) {
                continue;
            }
            $db->table('job_statuses')->insert([
                'uuid'               => $this->uuid($db),
                'workspace_id'       => null,
                'key'                => $key,
                'label'              => $label,
                'color'              => $color,
                'sort_order'         => $sort,
                'is_default'         => $isDefault,
                'is_initial'         => $isInitial,
                'is_terminal'        => $isTerminal,
                'is_published_state' => $isPublished,
                'is_system'          => 1,
                'created_at'         => $now,
                'updated_at'         => $now,
            ]);
        }
    }

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
