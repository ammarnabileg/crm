<?php

declare(strict_types=1);

use App\Core\Database;
use Database\Migration;

/**
 * D6 — Candidates (docs/database/07-Candidates.md). FOREIGN-KEY phase.
 *
 * Adds every FK for the nine D6 tables created in 0023_create_candidates.php.
 * Split into its own migration so all tables (and the cross-domain anchors
 * `users`, `workspaces`, `currencies`, `countries`, `languages`, `lookup_values`
 * from D0/D2, and D10 `files`) exist before any constraint is added — no
 * create-order or cross-domain cycle can break the build.
 *
 * FK policy (C-7 / DB-5): the owning person uses CASCADE; catalog / reference /
 * lookup refs use RESTRICT; optional file/actor refs use SET NULL — except
 * `candidate_documents.file_id` which is RESTRICT (the row is meaningless without
 * its file). `skills.workspace_id` is CASCADE (tenant-custom skills die with the
 * tenant; system skills have NULL `workspace_id` and are unaffected).
 *
 * `files` is owned by D10. If it has not been created yet at run time, its three
 * FKs (`certificates.file_id`, `candidate_documents.file_id`) are skipped with a
 * note and must be (re-)applied once D10 exists; all other FKs are unconditional.
 * Each ADD CONSTRAINT is guarded by `hasConstraint` for idempotency.
 */
return new class extends Migration {
    public function up(Database $db): void
    {
        // ---- candidate_profiles ------------------------------------------------
        $this->addFk($db, 'candidate_profiles', 'candidate_profiles_user_id_foreign', 'user_id', 'users', 'CASCADE', 'CASCADE');
        $this->addFk($db, 'candidate_profiles', 'candidate_profiles_expected_salary_currency_id_foreign', 'expected_salary_currency_id', 'currencies', 'RESTRICT', 'CASCADE');
        $this->addFk($db, 'candidate_profiles', 'candidate_profiles_availability_id_foreign', 'availability_id', 'lookup_values', 'RESTRICT', 'CASCADE');
        $this->addFk($db, 'candidate_profiles', 'candidate_profiles_expected_salary_period_id_foreign', 'expected_salary_period_id', 'lookup_values', 'RESTRICT', 'CASCADE');
        $this->addFk($db, 'candidate_profiles', 'candidate_profiles_gender_id_foreign', 'gender_id', 'lookup_values', 'RESTRICT', 'CASCADE');
        $this->addFk($db, 'candidate_profiles', 'candidate_profiles_nationality_country_id_foreign', 'nationality_country_id', 'countries', 'RESTRICT', 'CASCADE');
        $this->addFk($db, 'candidate_profiles', 'candidate_profiles_residence_country_id_foreign', 'residence_country_id', 'countries', 'RESTRICT', 'CASCADE');

        // ---- skills ------------------------------------------------------------
        $this->addFk($db, 'skills', 'skills_workspace_id_foreign', 'workspace_id', 'workspaces', 'CASCADE', 'CASCADE');
        $this->addFk($db, 'skills', 'skills_category_id_foreign', 'category_id', 'lookup_values', 'RESTRICT', 'CASCADE');
        $this->addFk($db, 'skills', 'skills_created_by_foreign', 'created_by', 'users', 'SET NULL', 'CASCADE');

        // ---- candidate_skills --------------------------------------------------
        $this->addFk($db, 'candidate_skills', 'candidate_skills_user_id_foreign', 'user_id', 'users', 'CASCADE', 'CASCADE');
        $this->addFk($db, 'candidate_skills', 'candidate_skills_skill_id_foreign', 'skill_id', 'skills', 'RESTRICT', 'CASCADE');
        $this->addFk($db, 'candidate_skills', 'candidate_skills_level_id_foreign', 'level_id', 'lookup_values', 'RESTRICT', 'CASCADE');

        // ---- candidate_languages ----------------------------------------------
        $this->addFk($db, 'candidate_languages', 'candidate_languages_user_id_foreign', 'user_id', 'users', 'CASCADE', 'CASCADE');
        $this->addFk($db, 'candidate_languages', 'candidate_languages_language_id_foreign', 'language_id', 'languages', 'RESTRICT', 'CASCADE');
        $this->addFk($db, 'candidate_languages', 'candidate_languages_proficiency_id_foreign', 'proficiency_id', 'lookup_values', 'RESTRICT', 'CASCADE');

        // ---- experiences -------------------------------------------------------
        $this->addFk($db, 'experiences', 'experiences_user_id_foreign', 'user_id', 'users', 'CASCADE', 'CASCADE');
        $this->addFk($db, 'experiences', 'experiences_employment_type_id_foreign', 'employment_type_id', 'lookup_values', 'RESTRICT', 'CASCADE');

        // ---- educations --------------------------------------------------------
        $this->addFk($db, 'educations', 'educations_user_id_foreign', 'user_id', 'users', 'CASCADE', 'CASCADE');
        $this->addFk($db, 'educations', 'educations_degree_level_id_foreign', 'degree_level_id', 'lookup_values', 'RESTRICT', 'CASCADE');

        // ---- certificates ------------------------------------------------------
        $this->addFk($db, 'certificates', 'certificates_user_id_foreign', 'user_id', 'users', 'CASCADE', 'CASCADE');
        $this->addFkIfRefExists($db, 'certificates', 'certificates_file_id_foreign', 'file_id', 'files', 'SET NULL', 'CASCADE');

        // ---- social_links ------------------------------------------------------
        $this->addFk($db, 'social_links', 'social_links_user_id_foreign', 'user_id', 'users', 'CASCADE', 'CASCADE');
        $this->addFk($db, 'social_links', 'social_links_platform_id_foreign', 'platform_id', 'lookup_values', 'RESTRICT', 'CASCADE');

        // ---- candidate_documents ----------------------------------------------
        $this->addFk($db, 'candidate_documents', 'candidate_documents_user_id_foreign', 'user_id', 'users', 'CASCADE', 'CASCADE');
        $this->addFkIfRefExists($db, 'candidate_documents', 'candidate_documents_file_id_foreign', 'file_id', 'files', 'RESTRICT', 'CASCADE');
        $this->addFk($db, 'candidate_documents', 'candidate_documents_type_id_foreign', 'type_id', 'lookup_values', 'RESTRICT', 'CASCADE');
        $this->addFk($db, 'candidate_documents', 'candidate_documents_language_id_foreign', 'language_id', 'languages', 'RESTRICT', 'CASCADE');
    }

    public function down(Database $db): void
    {
        $fks = [
            'candidate_profiles' => [
                'candidate_profiles_user_id_foreign',
                'candidate_profiles_expected_salary_currency_id_foreign',
                'candidate_profiles_availability_id_foreign',
                'candidate_profiles_expected_salary_period_id_foreign',
                'candidate_profiles_gender_id_foreign',
                'candidate_profiles_nationality_country_id_foreign',
                'candidate_profiles_residence_country_id_foreign',
            ],
            'skills' => [
                'skills_workspace_id_foreign',
                'skills_category_id_foreign',
                'skills_created_by_foreign',
            ],
            'candidate_skills' => [
                'candidate_skills_user_id_foreign',
                'candidate_skills_skill_id_foreign',
                'candidate_skills_level_id_foreign',
            ],
            'candidate_languages' => [
                'candidate_languages_user_id_foreign',
                'candidate_languages_language_id_foreign',
                'candidate_languages_proficiency_id_foreign',
            ],
            'experiences' => [
                'experiences_user_id_foreign',
                'experiences_employment_type_id_foreign',
            ],
            'educations' => [
                'educations_user_id_foreign',
                'educations_degree_level_id_foreign',
            ],
            'certificates' => [
                'certificates_user_id_foreign',
                'certificates_file_id_foreign',
            ],
            'social_links' => [
                'social_links_user_id_foreign',
                'social_links_platform_id_foreign',
            ],
            'candidate_documents' => [
                'candidate_documents_user_id_foreign',
                'candidate_documents_file_id_foreign',
                'candidate_documents_type_id_foreign',
                'candidate_documents_language_id_foreign',
            ],
        ];

        foreach ($fks as $table => $names) {
            if (! $this->hasTable($db, $table)) {
                continue;
            }
            foreach ($names as $fk) {
                if ($this->hasConstraint($db, $table, $fk)) {
                    $db->unprepared("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$fk}`");
                }
            }
        }
    }

    /** Add a FK (idempotent). */
    private function addFk(Database $db, string $table, string $fk, string $column, string $ref, string $onDelete, string $onUpdate): void
    {
        if (! $this->hasTable($db, $table) || $this->hasConstraint($db, $table, $fk)) {
            return;
        }
        $db->unprepared(
            "ALTER TABLE `{$table}` ADD CONSTRAINT `{$fk}` FOREIGN KEY (`{$column}`) "
            . "REFERENCES `{$ref}` (`id`) ON DELETE {$onDelete} ON UPDATE {$onUpdate}"
        );
    }

    /** Add a FK only when the referenced table already exists (cross-domain safety, e.g. D10 `files`). */
    private function addFkIfRefExists(Database $db, string $table, string $fk, string $column, string $ref, string $onDelete, string $onUpdate): void
    {
        if (! $this->hasTable($db, $ref)) {
            return;
        }
        $this->addFk($db, $table, $fk, $column, $ref, $onDelete, $onUpdate);
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
