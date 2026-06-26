<?php

declare(strict_types=1);

use App\Core\Database;
use Database\Migration;

/**
 * D6 — Candidates (docs/database/07-Candidates.md). CREATE phase.
 *
 * The candidate's portable, GLOBAL CV: profile + skills/languages pivots,
 * experiences, educations, certificates, social links and document registry.
 *
 * DB-1 invariant: a candidate IS a `users` row — there is no `candidates` table.
 * Every CV table here hangs off `user_id -> users(id)`; `candidate_profiles` is a
 * 1:1 extension of `users`. All CV tables are GLOBAL (NO `workspace_id`) except
 * the shared `skills` catalog (system + tenant-scoped; `workspace_id` NULL =
 * system skill, non-NULL = tenant custom). `skills` is owned here and reused by
 * D5 `job_skills`.
 *
 * Config-driven (DB-4, no ENUMs): availability / salary_period / gender /
 * skill_category / skill_level / language_proficiency / employment_type /
 * education_level / social_platform / candidate_document_type are all
 * `lookup_values` referenced by `<x>_id` FKs.
 *
 * Per the two-file pattern, this file creates STRUCTURE + ALL indexes only — it
 * adds NO foreign-key constraints (those live in 0123_fk_candidates.php). No
 * system rows are seeded: every D6 enumeration lives in `lookup_values`, seeded
 * centrally; this domain defines no `*_statuses`/catalog table of its own.
 * Idempotent via information_schema guards.
 */
return new class extends Migration {
    public function up(Database $db): void
    {
        $this->createCandidateProfiles($db);
        $this->createSkills($db);
        $this->createCandidateSkills($db);
        $this->createCandidateLanguages($db);
        $this->createExperiences($db);
        $this->createEducations($db);
        $this->createCertificates($db);
        $this->createSocialLinks($db);
        $this->createCandidateDocuments($db);

        // No seeding: all D6 enumerations live in `lookup_values` (seeded
        // centrally) and there is no `*_statuses` / catalog table in this domain.
    }

    public function down(Database $db): void
    {
        foreach ([
            'candidate_documents',
            'social_links',
            'certificates',
            'educations',
            'experiences',
            'candidate_languages',
            'candidate_skills',
            'skills',
            'candidate_profiles',
        ] as $table) {
            $db->unprepared("DROP TABLE IF EXISTS `{$table}`");
        }
    }

    /** 1. candidate_profiles — 1:1 extension of users; GLOBAL; soft-delete. */
    private function createCandidateProfiles(Database $db): void
    {
        if ($this->hasTable($db, 'candidate_profiles')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `candidate_profiles` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `user_id` BIGINT UNSIGNED NOT NULL,
                `headline` VARCHAR(180) NULL,
                `summary` TEXT NULL,
                `current_title` VARCHAR(150) NULL,
                `total_experience_years` DECIMAL(4,1) NULL,
                `availability_id` BIGINT UNSIGNED NULL,
                `notice_period_days` INT NULL,
                `expected_salary` DECIMAL(12,2) NULL,
                `expected_salary_currency_id` BIGINT UNSIGNED NULL,
                `expected_salary_period_id` BIGINT UNSIGNED NULL,
                `nationality_country_id` BIGINT UNSIGNED NULL,
                `residence_country_id` BIGINT UNSIGNED NULL,
                `city` VARCHAR(120) NULL,
                `date_of_birth` DATE NULL,
                `gender_id` BIGINT UNSIGNED NULL,
                `is_open_to_work` TINYINT(1) NOT NULL DEFAULT 1,
                `is_searchable` TINYINT(1) NOT NULL DEFAULT 0,
                `profile_completeness` TINYINT UNSIGNED NULL,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                `deleted_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_candidate_profiles_uuid` (`uuid`),
                UNIQUE KEY `uq_candidate_profiles_user` (`user_id`),
                KEY `ix_candidate_profiles_availability` (`availability_id`),
                KEY `ix_candidate_profiles_currency` (`expected_salary_currency_id`),
                KEY `ix_candidate_profiles_salary_period` (`expected_salary_period_id`),
                KEY `ix_candidate_profiles_gender` (`gender_id`),
                KEY `ix_candidate_profiles_nationality` (`nationality_country_id`),
                KEY `ix_candidate_profiles_residence` (`residence_country_id`),
                KEY `ix_candidate_profiles_searchable` (`is_searchable`, `is_open_to_work`),
                KEY `ix_candidate_profiles_deleted_at` (`deleted_at`),
                FULLTEXT KEY `ft_candidate_profiles_text` (`headline`, `summary`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    /** 2. skills — shared catalog (system + tenant-scoped); soft-delete. */
    private function createSkills(Database $db): void
    {
        if ($this->hasTable($db, 'skills')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `skills` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `workspace_id` BIGINT UNSIGNED NULL,
                `category_id` BIGINT UNSIGNED NULL,
                `name` VARCHAR(120) NOT NULL,
                `slug` VARCHAR(140) NOT NULL,
                `description` VARCHAR(255) NULL,
                `is_system` TINYINT(1) NOT NULL DEFAULT 0,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `usage_count` INT UNSIGNED NOT NULL DEFAULT 0,
                `created_by` BIGINT UNSIGNED NULL,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                `deleted_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_skills_uuid` (`uuid`),
                UNIQUE KEY `uq_skills_workspace_slug` (`workspace_id`, `slug`),
                KEY `ix_skills_category` (`category_id`),
                KEY `ix_skills_workspace_active` (`workspace_id`, `is_active`),
                KEY `ix_skills_created_by` (`created_by`),
                KEY `ix_skills_name` (`name`),
                KEY `ix_skills_deleted_at` (`deleted_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    /** 3. candidate_skills — pure pivot (user <-> skill); NO uuid; hard-delete. */
    private function createCandidateSkills(Database $db): void
    {
        if ($this->hasTable($db, 'candidate_skills')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `candidate_skills` (
                `user_id` BIGINT UNSIGNED NOT NULL,
                `skill_id` BIGINT UNSIGNED NOT NULL,
                `level_id` BIGINT UNSIGNED NULL,
                `years` DECIMAL(4,1) NULL,
                `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`user_id`, `skill_id`),
                KEY `ix_candidate_skills_skill` (`skill_id`),
                KEY `ix_candidate_skills_level` (`level_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    /** 4. candidate_languages — pure pivot (user <-> language); NO uuid; hard-delete. */
    private function createCandidateLanguages(Database $db): void
    {
        if ($this->hasTable($db, 'candidate_languages')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `candidate_languages` (
                `user_id` BIGINT UNSIGNED NOT NULL,
                `language_id` BIGINT UNSIGNED NOT NULL,
                `proficiency_id` BIGINT UNSIGNED NULL,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`user_id`, `language_id`),
                KEY `ix_candidate_languages_language` (`language_id`),
                KEY `ix_candidate_languages_proficiency` (`proficiency_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    /** 5. experiences — work history; GLOBAL; soft-delete. */
    private function createExperiences(Database $db): void
    {
        if ($this->hasTable($db, 'experiences')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `experiences` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `user_id` BIGINT UNSIGNED NOT NULL,
                `company_name` VARCHAR(180) NOT NULL,
                `title` VARCHAR(150) NOT NULL,
                `employment_type_id` BIGINT UNSIGNED NULL,
                `location` VARCHAR(180) NULL,
                `is_current` TINYINT(1) NOT NULL DEFAULT 0,
                `start_date` DATE NOT NULL,
                `end_date` DATE NULL,
                `description` TEXT NULL,
                `sort_order` INT NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                `deleted_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_experiences_uuid` (`uuid`),
                KEY `ix_experiences_user` (`user_id`, `start_date`),
                KEY `ix_experiences_employment_type` (`employment_type_id`),
                KEY `ix_experiences_deleted_at` (`deleted_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    /** 6. educations — academic history; GLOBAL; soft-delete. */
    private function createEducations(Database $db): void
    {
        if ($this->hasTable($db, 'educations')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `educations` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `user_id` BIGINT UNSIGNED NOT NULL,
                `institution` VARCHAR(200) NOT NULL,
                `degree` VARCHAR(150) NULL,
                `field_of_study` VARCHAR(180) NULL,
                `degree_level_id` BIGINT UNSIGNED NULL,
                `grade` VARCHAR(60) NULL,
                `start_date` DATE NULL,
                `end_date` DATE NULL,
                `is_current` TINYINT(1) NOT NULL DEFAULT 0,
                `description` TEXT NULL,
                `sort_order` INT NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                `deleted_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_educations_uuid` (`uuid`),
                KEY `ix_educations_user` (`user_id`, `end_date`),
                KEY `ix_educations_degree_level` (`degree_level_id`),
                KEY `ix_educations_deleted_at` (`deleted_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    /** 7. certificates — certifications/licenses; GLOBAL; soft-delete. */
    private function createCertificates(Database $db): void
    {
        if ($this->hasTable($db, 'certificates')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `certificates` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `user_id` BIGINT UNSIGNED NOT NULL,
                `name` VARCHAR(200) NOT NULL,
                `issuer` VARCHAR(200) NULL,
                `credential_id` VARCHAR(180) NULL,
                `credential_url` VARCHAR(500) NULL,
                `issue_date` DATE NULL,
                `expiry_date` DATE NULL,
                `does_not_expire` TINYINT(1) NOT NULL DEFAULT 0,
                `file_id` BIGINT UNSIGNED NULL,
                `sort_order` INT NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                `deleted_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_certificates_uuid` (`uuid`),
                KEY `ix_certificates_user` (`user_id`, `issue_date`),
                KEY `ix_certificates_expiry` (`expiry_date`),
                KEY `ix_certificates_file` (`file_id`),
                KEY `ix_certificates_deleted_at` (`deleted_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    /** 8. social_links — external profile/portfolio links; GLOBAL; soft-delete. */
    private function createSocialLinks(Database $db): void
    {
        if ($this->hasTable($db, 'social_links')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `social_links` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `user_id` BIGINT UNSIGNED NOT NULL,
                `platform_id` BIGINT UNSIGNED NOT NULL,
                `url` VARCHAR(500) NOT NULL,
                `label` VARCHAR(120) NULL,
                `sort_order` INT NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                `deleted_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_social_links_uuid` (`uuid`),
                UNIQUE KEY `uq_social_links_user_platform` (`user_id`, `platform_id`),
                KEY `ix_social_links_platform` (`platform_id`),
                KEY `ix_social_links_deleted_at` (`deleted_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    /** 9. candidate_documents — reusable CV/portfolio document registry; soft-delete. */
    private function createCandidateDocuments(Database $db): void
    {
        if ($this->hasTable($db, 'candidate_documents')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `candidate_documents` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `user_id` BIGINT UNSIGNED NOT NULL,
                `file_id` BIGINT UNSIGNED NOT NULL,
                `type_id` BIGINT UNSIGNED NOT NULL,
                `title` VARCHAR(180) NULL,
                `language_id` BIGINT UNSIGNED NULL,
                `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                `deleted_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_candidate_documents_uuid` (`uuid`),
                KEY `ix_candidate_documents_user_type` (`user_id`, `type_id`),
                KEY `ix_candidate_documents_file` (`file_id`),
                KEY `ix_candidate_documents_type` (`type_id`),
                KEY `ix_candidate_documents_language` (`language_id`),
                KEY `ix_candidate_documents_primary` (`user_id`, `type_id`, `is_primary`),
                KEY `ix_candidate_documents_deleted_at` (`deleted_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
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
