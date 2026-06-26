<?php

declare(strict_types=1);

use App\Core\Database;
use Database\Migration;

/**
 * D5 — Jobs: foreign keys (paired with 0024_create_jobs.php).
 *
 * TWO-FILE pattern: 0024 created all twelve tables + indexes with NO FK
 * constraints; this file adds every FK (anchors, intra-domain, and cross-domain)
 * so create-order and cross-domain cycles never break the build. In particular
 * the jobs<->pipelines soft cycle is resolved here by adding BOTH directions
 * (`jobs.pipeline_id -> pipelines` and `pipelines.job_id -> jobs`) only after
 * both tables already exist.
 *
 * Cross-domain / built-table targets (exist by the time the lead runs this file):
 *  - BUILT (0001/0017/0018): `workspaces`, `users`, `currencies`, `countries`,
 *    `timezones`, `languages`, `lookup_values`.
 *  - D6  `skills`               <- job_skills.skill_id
 *  - D7  `application_statuses`  <- pipeline_stages.application_status_id
 *  - D9  `departments`          <- jobs.department_id
 *
 * Every ADD/DROP CONSTRAINT is guarded with hasConstraint (idempotent).
 */
return new class extends Migration {
    /**
     * Every FK: [table, constraint, column, ref_table, ref_col, on_delete, on_update].
     */
    private function foreignKeys(): array
    {
        return [
            // --- jobs ---
            ['jobs', 'jobs_workspace_id_foreign', 'workspace_id', 'workspaces', 'id', 'CASCADE', 'CASCADE'],
            ['jobs', 'jobs_job_status_id_foreign', 'job_status_id', 'job_statuses', 'id', 'RESTRICT', 'CASCADE'],
            ['jobs', 'jobs_pipeline_id_foreign', 'pipeline_id', 'pipelines', 'id', 'SET NULL', 'CASCADE'],
            ['jobs', 'jobs_department_id_foreign', 'department_id', 'departments', 'id', 'SET NULL', 'CASCADE'],
            ['jobs', 'jobs_employment_type_id_foreign', 'employment_type_id', 'lookup_values', 'id', 'RESTRICT', 'CASCADE'],
            ['jobs', 'jobs_experience_level_id_foreign', 'experience_level_id', 'lookup_values', 'id', 'RESTRICT', 'CASCADE'],
            ['jobs', 'jobs_currency_id_foreign', 'currency_id', 'currencies', 'id', 'RESTRICT', 'CASCADE'],
            ['jobs', 'jobs_salary_period_id_foreign', 'salary_period_id', 'lookup_values', 'id', 'RESTRICT', 'CASCADE'],
            ['jobs', 'jobs_created_by_foreign', 'created_by', 'users', 'id', 'SET NULL', 'CASCADE'],
            ['jobs', 'jobs_updated_by_foreign', 'updated_by', 'users', 'id', 'SET NULL', 'CASCADE'],

            // --- job_statuses ---
            ['job_statuses', 'job_statuses_workspace_id_foreign', 'workspace_id', 'workspaces', 'id', 'CASCADE', 'CASCADE'],

            // --- locations ---
            ['locations', 'locations_workspace_id_foreign', 'workspace_id', 'workspaces', 'id', 'CASCADE', 'CASCADE'],
            ['locations', 'locations_country_id_foreign', 'country_id', 'countries', 'id', 'RESTRICT', 'CASCADE'],
            ['locations', 'locations_timezone_id_foreign', 'timezone_id', 'timezones', 'id', 'RESTRICT', 'CASCADE'],

            // --- job_locations ---
            ['job_locations', 'job_locations_workspace_id_foreign', 'workspace_id', 'workspaces', 'id', 'CASCADE', 'CASCADE'],
            ['job_locations', 'job_locations_job_id_foreign', 'job_id', 'jobs', 'id', 'CASCADE', 'CASCADE'],
            ['job_locations', 'job_locations_location_id_foreign', 'location_id', 'locations', 'id', 'CASCADE', 'CASCADE'],

            // --- job_skills (skill_id -> D6 skills) ---
            ['job_skills', 'job_skills_workspace_id_foreign', 'workspace_id', 'workspaces', 'id', 'CASCADE', 'CASCADE'],
            ['job_skills', 'job_skills_job_id_foreign', 'job_id', 'jobs', 'id', 'CASCADE', 'CASCADE'],
            ['job_skills', 'job_skills_skill_id_foreign', 'skill_id', 'skills', 'id', 'RESTRICT', 'CASCADE'],
            ['job_skills', 'job_skills_required_level_id_foreign', 'required_level_id', 'lookup_values', 'id', 'RESTRICT', 'CASCADE'],

            // --- job_languages (language_id -> D0 languages) ---
            ['job_languages', 'job_languages_workspace_id_foreign', 'workspace_id', 'workspaces', 'id', 'CASCADE', 'CASCADE'],
            ['job_languages', 'job_languages_job_id_foreign', 'job_id', 'jobs', 'id', 'CASCADE', 'CASCADE'],
            ['job_languages', 'job_languages_language_id_foreign', 'language_id', 'languages', 'id', 'RESTRICT', 'CASCADE'],
            ['job_languages', 'job_languages_proficiency_id_foreign', 'proficiency_id', 'lookup_values', 'id', 'RESTRICT', 'CASCADE'],

            // --- benefits ---
            ['benefits', 'benefits_workspace_id_foreign', 'workspace_id', 'workspaces', 'id', 'CASCADE', 'CASCADE'],
            ['benefits', 'benefits_category_id_foreign', 'category_id', 'lookup_values', 'id', 'RESTRICT', 'CASCADE'],

            // --- job_benefits ---
            ['job_benefits', 'job_benefits_workspace_id_foreign', 'workspace_id', 'workspaces', 'id', 'CASCADE', 'CASCADE'],
            ['job_benefits', 'job_benefits_job_id_foreign', 'job_id', 'jobs', 'id', 'CASCADE', 'CASCADE'],
            ['job_benefits', 'job_benefits_benefit_id_foreign', 'benefit_id', 'benefits', 'id', 'RESTRICT', 'CASCADE'],

            // --- job_questions ---
            ['job_questions', 'job_questions_workspace_id_foreign', 'workspace_id', 'workspaces', 'id', 'CASCADE', 'CASCADE'],
            ['job_questions', 'job_questions_job_id_foreign', 'job_id', 'jobs', 'id', 'CASCADE', 'CASCADE'],
            ['job_questions', 'job_questions_question_type_id_foreign', 'question_type_id', 'lookup_values', 'id', 'RESTRICT', 'CASCADE'],

            // --- job_criteria ---
            ['job_criteria', 'job_criteria_workspace_id_foreign', 'workspace_id', 'workspaces', 'id', 'CASCADE', 'CASCADE'],
            ['job_criteria', 'job_criteria_job_id_foreign', 'job_id', 'jobs', 'id', 'CASCADE', 'CASCADE'],
            ['job_criteria', 'job_criteria_criterion_type_id_foreign', 'criterion_type_id', 'lookup_values', 'id', 'RESTRICT', 'CASCADE'],

            // --- pipelines (job_id -> jobs completes the soft cycle) ---
            ['pipelines', 'pipelines_workspace_id_foreign', 'workspace_id', 'workspaces', 'id', 'CASCADE', 'CASCADE'],
            ['pipelines', 'pipelines_job_id_foreign', 'job_id', 'jobs', 'id', 'CASCADE', 'CASCADE'],
            ['pipelines', 'pipelines_created_by_foreign', 'created_by', 'users', 'id', 'SET NULL', 'CASCADE'],

            // --- pipeline_stages (application_status_id -> D7 application_statuses) ---
            ['pipeline_stages', 'pipeline_stages_workspace_id_foreign', 'workspace_id', 'workspaces', 'id', 'CASCADE', 'CASCADE'],
            ['pipeline_stages', 'pipeline_stages_pipeline_id_foreign', 'pipeline_id', 'pipelines', 'id', 'CASCADE', 'CASCADE'],
            ['pipeline_stages', 'pipeline_stages_application_status_id_foreign', 'application_status_id', 'application_statuses', 'id', 'RESTRICT', 'CASCADE'],
            ['pipeline_stages', 'pipeline_stages_stage_type_id_foreign', 'stage_type_id', 'lookup_values', 'id', 'RESTRICT', 'CASCADE'],
        ];
    }

    public function up(Database $db): void
    {
        foreach ($this->foreignKeys() as [$table, $constraint, $column, $refTable, $refCol, $onDelete, $onUpdate]) {
            if (! $this->hasTable($db, $table)) {
                continue;
            }
            if ($this->hasConstraint($db, $table, $constraint)) {
                continue;
            }
            $db->unprepared(
                "ALTER TABLE `{$table}` ADD CONSTRAINT `{$constraint}` FOREIGN KEY (`{$column}`) "
                . "REFERENCES `{$refTable}` (`{$refCol}`) ON DELETE {$onDelete} ON UPDATE {$onUpdate}"
            );
        }
    }

    public function down(Database $db): void
    {
        foreach ($this->foreignKeys() as [$table, $constraint]) {
            if (! $this->hasTable($db, $table)) {
                continue;
            }
            if ($this->hasConstraint($db, $table, $constraint)) {
                $db->unprepared("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$constraint}`");
            }
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
