<?php

declare(strict_types=1);

use App\Core\Database;
use Database\Migration;

/**
 * D9 — HR & Talent Pool (structure only; NO foreign keys).
 *
 * Creates the organizational structure (departments, teams + members),
 * interview logistics (panels + members, schedules, meetings), configurable
 * scorecards (evaluation_forms + fields + evaluations + scores), the offer
 * lifecycle (offers + offer_statuses + offer_approvals), the generic
 * configurable approval engine (approvals + approval_steps) and the talent
 * pool (pools + pool_groups + pool_candidates).
 *
 * Per the two-file build pattern, this file owns COLUMNS + ALL INDEXES (PK,
 * uuid UNIQUE, business uniques, an index on every FK column, composites and
 * the polymorphic (type,id) index) but declares NO FOREIGN KEY CONSTRAINTS —
 * those live in 0127_fk_hr_talent.php so create-order/cross-domain cycles can
 * never break the build. It then seeds the `offer_statuses` system defaults.
 *
 * Tenant column is `workspace_id` → `workspaces`. Configuration-driven: the
 * only status table here is `offer_statuses`; every other type/role/status/
 * recommendation/mode/source/stage is a `lookup_values` FK (no ENUMs). All
 * guards use information_schema so the migration is idempotent.
 *
 * NOTE: the D9 design doc (docs/database/10-HR-Talent.md) also describes a
 * `meeting_participants` pivot, but the build task enumerates exactly the
 * nineteen tables created here and excludes it; it is therefore intentionally
 * not created in this migration.
 */
return new class extends Migration {
    /**
     * System-default offer workflow statuses (workspace_id NULL, is_system=1).
     * [key, label, color, sort, is_default, is_initial, is_terminal]
     */
    private array $offerStatuses = [
        ['draft',            'Draft',            '#9ca3af', 1, 1, 1, 0],
        ['pending_approval', 'Pending Approval', '#f59e0b', 2, 0, 0, 0],
        ['approved',         'Approved',         '#3b82f6', 3, 0, 0, 0],
        ['sent',             'Sent',             '#6366f1', 4, 0, 0, 0],
        ['accepted',         'Accepted',         '#16a34a', 5, 0, 0, 1],
        ['declined',         'Declined',         '#dc2626', 6, 0, 0, 1],
        ['rescinded',        'Rescinded',        '#b91c1c', 7, 0, 0, 1],
        ['expired',          'Expired',          '#71717a', 8, 0, 0, 1],
    ];

    public function up(Database $db): void
    {
        $this->createDepartments($db);
        $this->createTeams($db);
        $this->createTeamMembers($db);
        $this->createInterviewPanels($db);
        $this->createPanelMembers($db);
        $this->createSchedules($db);
        $this->createMeetings($db);
        $this->createEvaluationForms($db);
        $this->createEvaluationFormFields($db);
        $this->createEvaluations($db);
        $this->createEvaluationScores($db);
        $this->createOfferStatuses($db);
        $this->createOffers($db);
        $this->createOfferApprovals($db);
        $this->createApprovals($db);
        $this->createApprovalSteps($db);
        $this->createPools($db);
        $this->createPoolGroups($db);
        $this->createPoolCandidates($db);

        $this->seedOfferStatuses($db);
    }

    public function down(Database $db): void
    {
        // Drop in reverse dependency order (intra-domain children first).
        foreach ([
            'pool_candidates',
            'pool_groups',
            'pools',
            'approval_steps',
            'approvals',
            'offer_approvals',
            'offers',
            'offer_statuses',
            'evaluation_scores',
            'evaluations',
            'evaluation_form_fields',
            'evaluation_forms',
            'meetings',
            'schedules',
            'panel_members',
            'interview_panels',
            'team_members',
            'teams',
            'departments',
        ] as $table) {
            $db->unprepared("DROP TABLE IF EXISTS `{$table}`");
        }
    }

    // -----------------------------------------------------------------
    // 1. departments
    // -----------------------------------------------------------------
    private function createDepartments(Database $db): void
    {
        if ($this->hasTable($db, 'departments')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `departments` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `workspace_id` BIGINT UNSIGNED NOT NULL,
                `parent_id` BIGINT UNSIGNED NULL,
                `head_user_id` BIGINT UNSIGNED NULL,
                `name` VARCHAR(150) NOT NULL,
                `slug` VARCHAR(160) NOT NULL,
                `code` VARCHAR(40) NULL,
                `description` TEXT NULL,
                `path` VARCHAR(255) NULL,
                `depth` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                `sort_order` INT NOT NULL DEFAULT 0,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `created_by` BIGINT UNSIGNED NULL,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                `deleted_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `departments_uuid_unique` (`uuid`),
                UNIQUE KEY `departments_workspace_slug_unique` (`workspace_id`, `slug`),
                KEY `departments_workspace_id_index` (`workspace_id`),
                KEY `departments_parent_id_index` (`parent_id`),
                KEY `departments_head_user_id_index` (`head_user_id`),
                KEY `departments_created_by_index` (`created_by`),
                KEY `departments_workspace_parent_index` (`workspace_id`, `parent_id`),
                KEY `departments_workspace_active_index` (`workspace_id`, `is_active`),
                KEY `departments_path_index` (`path`),
                KEY `departments_deleted_at_index` (`deleted_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    // -----------------------------------------------------------------
    // 2. teams
    // -----------------------------------------------------------------
    private function createTeams(Database $db): void
    {
        if ($this->hasTable($db, 'teams')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `teams` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `workspace_id` BIGINT UNSIGNED NOT NULL,
                `department_id` BIGINT UNSIGNED NULL,
                `lead_user_id` BIGINT UNSIGNED NULL,
                `name` VARCHAR(150) NOT NULL,
                `slug` VARCHAR(160) NOT NULL,
                `description` TEXT NULL,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `created_by` BIGINT UNSIGNED NULL,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                `deleted_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `teams_uuid_unique` (`uuid`),
                UNIQUE KEY `teams_workspace_slug_unique` (`workspace_id`, `slug`),
                KEY `teams_workspace_id_index` (`workspace_id`),
                KEY `teams_department_id_index` (`department_id`),
                KEY `teams_lead_user_id_index` (`lead_user_id`),
                KEY `teams_created_by_index` (`created_by`),
                KEY `teams_workspace_active_index` (`workspace_id`, `is_active`),
                KEY `teams_deleted_at_index` (`deleted_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    // -----------------------------------------------------------------
    // 3. team_members (pivot, no soft-delete)
    // -----------------------------------------------------------------
    private function createTeamMembers(Database $db): void
    {
        if ($this->hasTable($db, 'team_members')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `team_members` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `workspace_id` BIGINT UNSIGNED NOT NULL,
                `team_id` BIGINT UNSIGNED NOT NULL,
                `user_id` BIGINT UNSIGNED NOT NULL,
                `role_id` BIGINT UNSIGNED NULL,
                `joined_at` TIMESTAMP NULL DEFAULT NULL,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `team_members_uuid_unique` (`uuid`),
                UNIQUE KEY `team_members_team_user_unique` (`team_id`, `user_id`),
                KEY `team_members_workspace_id_index` (`workspace_id`),
                KEY `team_members_team_id_index` (`team_id`),
                KEY `team_members_user_id_index` (`user_id`),
                KEY `team_members_role_id_index` (`role_id`),
                KEY `team_members_user_active_index` (`user_id`, `is_active`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    // -----------------------------------------------------------------
    // 4. interview_panels
    // -----------------------------------------------------------------
    private function createInterviewPanels(Database $db): void
    {
        if ($this->hasTable($db, 'interview_panels')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `interview_panels` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `workspace_id` BIGINT UNSIGNED NOT NULL,
                `interview_id` BIGINT UNSIGNED NULL,
                `name` VARCHAR(150) NOT NULL,
                `chair_user_id` BIGINT UNSIGNED NULL,
                `notes` TEXT NULL,
                `is_template` TINYINT(1) NOT NULL DEFAULT 0,
                `created_by` BIGINT UNSIGNED NULL,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                `deleted_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `interview_panels_uuid_unique` (`uuid`),
                UNIQUE KEY `interview_panels_interview_unique` (`interview_id`),
                KEY `interview_panels_workspace_id_index` (`workspace_id`),
                KEY `interview_panels_interview_id_index` (`interview_id`),
                KEY `interview_panels_chair_user_id_index` (`chair_user_id`),
                KEY `interview_panels_created_by_index` (`created_by`),
                KEY `interview_panels_workspace_template_index` (`workspace_id`, `is_template`),
                KEY `interview_panels_deleted_at_index` (`deleted_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    // -----------------------------------------------------------------
    // 5. panel_members (pivot, no soft-delete)
    // -----------------------------------------------------------------
    private function createPanelMembers(Database $db): void
    {
        if ($this->hasTable($db, 'panel_members')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `panel_members` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `workspace_id` BIGINT UNSIGNED NOT NULL,
                `panel_id` BIGINT UNSIGNED NOT NULL,
                `user_id` BIGINT UNSIGNED NOT NULL,
                `role_id` BIGINT UNSIGNED NULL,
                `is_required` TINYINT(1) NOT NULL DEFAULT 1,
                `invited_at` TIMESTAMP NULL DEFAULT NULL,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `panel_members_uuid_unique` (`uuid`),
                UNIQUE KEY `panel_members_panel_user_unique` (`panel_id`, `user_id`),
                KEY `panel_members_workspace_id_index` (`workspace_id`),
                KEY `panel_members_panel_id_index` (`panel_id`),
                KEY `panel_members_user_id_index` (`user_id`),
                KEY `panel_members_role_id_index` (`role_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    // -----------------------------------------------------------------
    // 6. schedules
    // -----------------------------------------------------------------
    private function createSchedules(Database $db): void
    {
        if ($this->hasTable($db, 'schedules')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `schedules` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `workspace_id` BIGINT UNSIGNED NOT NULL,
                `owner_user_id` BIGINT UNSIGNED NULL,
                `team_id` BIGINT UNSIGNED NULL,
                `name` VARCHAR(150) NOT NULL,
                `timezone_id` BIGINT UNSIGNED NULL,
                `color` VARCHAR(20) NULL,
                `visibility_id` BIGINT UNSIGNED NULL,
                `is_default` TINYINT(1) NOT NULL DEFAULT 0,
                `created_by` BIGINT UNSIGNED NULL,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                `deleted_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `schedules_uuid_unique` (`uuid`),
                KEY `schedules_workspace_id_index` (`workspace_id`),
                KEY `schedules_owner_user_id_index` (`owner_user_id`),
                KEY `schedules_team_id_index` (`team_id`),
                KEY `schedules_timezone_id_index` (`timezone_id`),
                KEY `schedules_visibility_id_index` (`visibility_id`),
                KEY `schedules_created_by_index` (`created_by`),
                KEY `schedules_workspace_owner_index` (`workspace_id`, `owner_user_id`),
                KEY `schedules_deleted_at_index` (`deleted_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    // -----------------------------------------------------------------
    // 7. meetings
    // -----------------------------------------------------------------
    private function createMeetings(Database $db): void
    {
        if ($this->hasTable($db, 'meetings')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `meetings` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `workspace_id` BIGINT UNSIGNED NOT NULL,
                `schedule_id` BIGINT UNSIGNED NULL,
                `interview_id` BIGINT UNSIGNED NULL,
                `organizer_user_id` BIGINT UNSIGNED NULL,
                `title` VARCHAR(200) NOT NULL,
                `description` TEXT NULL,
                `type_id` BIGINT UNSIGNED NULL,
                `status_id` BIGINT UNSIGNED NULL,
                `location_type_id` BIGINT UNSIGNED NULL,
                `location` VARCHAR(255) NULL,
                `meeting_url` VARCHAR(512) NULL,
                `meeting_provider_id` BIGINT UNSIGNED NULL,
                `starts_at` DATETIME NOT NULL,
                `ends_at` DATETIME NULL,
                `timezone_id` BIGINT UNSIGNED NULL,
                `all_day` TINYINT(1) NOT NULL DEFAULT 0,
                `created_by` BIGINT UNSIGNED NULL,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                `deleted_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `meetings_uuid_unique` (`uuid`),
                KEY `meetings_workspace_id_index` (`workspace_id`),
                KEY `meetings_schedule_id_index` (`schedule_id`),
                KEY `meetings_interview_id_index` (`interview_id`),
                KEY `meetings_organizer_user_id_index` (`organizer_user_id`),
                KEY `meetings_type_id_index` (`type_id`),
                KEY `meetings_status_id_index` (`status_id`),
                KEY `meetings_location_type_id_index` (`location_type_id`),
                KEY `meetings_meeting_provider_id_index` (`meeting_provider_id`),
                KEY `meetings_timezone_id_index` (`timezone_id`),
                KEY `meetings_created_by_index` (`created_by`),
                KEY `meetings_workspace_starts_index` (`workspace_id`, `starts_at`),
                KEY `meetings_workspace_status_index` (`workspace_id`, `status_id`),
                KEY `meetings_deleted_at_index` (`deleted_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    // -----------------------------------------------------------------
    // 8. evaluation_forms
    // -----------------------------------------------------------------
    private function createEvaluationForms(Database $db): void
    {
        if ($this->hasTable($db, 'evaluation_forms')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `evaluation_forms` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `workspace_id` BIGINT UNSIGNED NOT NULL,
                `name` VARCHAR(150) NOT NULL,
                `slug` VARCHAR(160) NOT NULL,
                `description` TEXT NULL,
                `scope_id` BIGINT UNSIGNED NULL,
                `scoring_type_id` BIGINT UNSIGNED NULL,
                `max_score` DECIMAL(6,2) NULL,
                `pass_threshold` DECIMAL(6,2) NULL,
                `version` INT UNSIGNED NOT NULL DEFAULT 1,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `is_default` TINYINT(1) NOT NULL DEFAULT 0,
                `created_by` BIGINT UNSIGNED NULL,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                `deleted_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `evaluation_forms_uuid_unique` (`uuid`),
                UNIQUE KEY `evaluation_forms_workspace_slug_unique` (`workspace_id`, `slug`),
                KEY `evaluation_forms_workspace_id_index` (`workspace_id`),
                KEY `evaluation_forms_scope_id_index` (`scope_id`),
                KEY `evaluation_forms_scoring_type_id_index` (`scoring_type_id`),
                KEY `evaluation_forms_created_by_index` (`created_by`),
                KEY `evaluation_forms_workspace_active_index` (`workspace_id`, `is_active`),
                KEY `evaluation_forms_deleted_at_index` (`deleted_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    // -----------------------------------------------------------------
    // 9. evaluation_form_fields
    // -----------------------------------------------------------------
    private function createEvaluationFormFields(Database $db): void
    {
        if ($this->hasTable($db, 'evaluation_form_fields')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `evaluation_form_fields` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `workspace_id` BIGINT UNSIGNED NOT NULL,
                `form_id` BIGINT UNSIGNED NOT NULL,
                `label` VARCHAR(200) NOT NULL,
                `key` VARCHAR(60) NOT NULL,
                `description` TEXT NULL,
                `field_type_id` BIGINT UNSIGNED NOT NULL,
                `category_id` BIGINT UNSIGNED NULL,
                `weight` DECIMAL(6,3) NOT NULL DEFAULT 1.000,
                `max_value` DECIMAL(6,2) NULL,
                `min_value` DECIMAL(6,2) NULL,
                `options` JSON NULL,
                `is_required` TINYINT(1) NOT NULL DEFAULT 1,
                `sort_order` INT NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                `deleted_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `evaluation_form_fields_uuid_unique` (`uuid`),
                UNIQUE KEY `evaluation_form_fields_form_key_unique` (`form_id`, `key`),
                KEY `evaluation_form_fields_workspace_id_index` (`workspace_id`),
                KEY `evaluation_form_fields_form_id_index` (`form_id`),
                KEY `evaluation_form_fields_field_type_id_index` (`field_type_id`),
                KEY `evaluation_form_fields_category_id_index` (`category_id`),
                KEY `evaluation_form_fields_form_sort_index` (`form_id`, `sort_order`),
                KEY `evaluation_form_fields_deleted_at_index` (`deleted_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    // -----------------------------------------------------------------
    // 10. evaluations
    // -----------------------------------------------------------------
    private function createEvaluations(Database $db): void
    {
        if ($this->hasTable($db, 'evaluations')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `evaluations` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `workspace_id` BIGINT UNSIGNED NOT NULL,
                `application_id` BIGINT UNSIGNED NOT NULL,
                `interview_id` BIGINT UNSIGNED NULL,
                `form_id` BIGINT UNSIGNED NULL,
                `evaluator_id` BIGINT UNSIGNED NULL,
                `recommendation_id` BIGINT UNSIGNED NULL,
                `overall_score` DECIMAL(6,2) NULL,
                `normalized_score` DECIMAL(5,2) NULL,
                `summary` TEXT NULL,
                `submitted_at` TIMESTAMP NULL DEFAULT NULL,
                `is_draft` TINYINT(1) NOT NULL DEFAULT 1,
                `created_by` BIGINT UNSIGNED NULL,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                `deleted_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `evaluations_uuid_unique` (`uuid`),
                UNIQUE KEY `evaluations_app_int_eval_form_unique` (`application_id`, `interview_id`, `evaluator_id`, `form_id`),
                KEY `evaluations_workspace_id_index` (`workspace_id`),
                KEY `evaluations_application_id_index` (`application_id`),
                KEY `evaluations_interview_id_index` (`interview_id`),
                KEY `evaluations_form_id_index` (`form_id`),
                KEY `evaluations_evaluator_id_index` (`evaluator_id`),
                KEY `evaluations_recommendation_id_index` (`recommendation_id`),
                KEY `evaluations_created_by_index` (`created_by`),
                KEY `evaluations_workspace_application_index` (`workspace_id`, `application_id`),
                KEY `evaluations_deleted_at_index` (`deleted_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    // -----------------------------------------------------------------
    // 11. evaluation_scores (child, no soft-delete)
    // -----------------------------------------------------------------
    private function createEvaluationScores(Database $db): void
    {
        if ($this->hasTable($db, 'evaluation_scores')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `evaluation_scores` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `workspace_id` BIGINT UNSIGNED NOT NULL,
                `evaluation_id` BIGINT UNSIGNED NOT NULL,
                `field_id` BIGINT UNSIGNED NOT NULL,
                `value_numeric` DECIMAL(8,3) NULL,
                `value_bool` TINYINT(1) NULL,
                `value_text` TEXT NULL,
                `value_option_id` BIGINT UNSIGNED NULL,
                `weighted_score` DECIMAL(8,3) NULL,
                `comment` TEXT NULL,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `evaluation_scores_uuid_unique` (`uuid`),
                UNIQUE KEY `evaluation_scores_eval_field_unique` (`evaluation_id`, `field_id`),
                KEY `evaluation_scores_workspace_id_index` (`workspace_id`),
                KEY `evaluation_scores_evaluation_id_index` (`evaluation_id`),
                KEY `evaluation_scores_field_id_index` (`field_id`),
                KEY `evaluation_scores_value_option_id_index` (`value_option_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    // -----------------------------------------------------------------
    // 12. offer_statuses (config status table; no soft-delete)
    // -----------------------------------------------------------------
    private function createOfferStatuses(Database $db): void
    {
        if ($this->hasTable($db, 'offer_statuses')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `offer_statuses` (
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
                `is_system` TINYINT(1) NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `offer_statuses_uuid_unique` (`uuid`),
                UNIQUE KEY `offer_statuses_workspace_key_unique` (`workspace_id`, `key`),
                KEY `offer_statuses_workspace_id_index` (`workspace_id`),
                KEY `offer_statuses_workspace_sort_index` (`workspace_id`, `sort_order`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    // -----------------------------------------------------------------
    // 13. offers
    // -----------------------------------------------------------------
    private function createOffers(Database $db): void
    {
        if ($this->hasTable($db, 'offers')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `offers` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `workspace_id` BIGINT UNSIGNED NOT NULL,
                `application_id` BIGINT UNSIGNED NOT NULL,
                `offer_status_id` BIGINT UNSIGNED NOT NULL,
                `candidate_user_id` BIGINT UNSIGNED NULL,
                `job_title` VARCHAR(200) NULL,
                `employment_type_id` BIGINT UNSIGNED NULL,
                `salary_amount` DECIMAL(12,2) NULL,
                `currency_id` BIGINT UNSIGNED NULL,
                `salary_period_id` BIGINT UNSIGNED NULL,
                `bonus_amount` DECIMAL(12,2) NULL,
                `equity` VARCHAR(120) NULL,
                `start_date` DATE NULL,
                `expires_at` DATETIME NULL,
                `sent_at` TIMESTAMP NULL DEFAULT NULL,
                `responded_at` TIMESTAMP NULL DEFAULT NULL,
                `notes` TEXT NULL,
                `created_by` BIGINT UNSIGNED NULL,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                `deleted_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `offers_uuid_unique` (`uuid`),
                KEY `offers_workspace_id_index` (`workspace_id`),
                KEY `offers_application_id_index` (`application_id`),
                KEY `offers_offer_status_id_index` (`offer_status_id`),
                KEY `offers_candidate_user_id_index` (`candidate_user_id`),
                KEY `offers_employment_type_id_index` (`employment_type_id`),
                KEY `offers_currency_id_index` (`currency_id`),
                KEY `offers_salary_period_id_index` (`salary_period_id`),
                KEY `offers_created_by_index` (`created_by`),
                KEY `offers_workspace_status_index` (`workspace_id`, `offer_status_id`),
                KEY `offers_workspace_expires_index` (`workspace_id`, `expires_at`),
                KEY `offers_deleted_at_index` (`deleted_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    // -----------------------------------------------------------------
    // 14. offer_approvals (link row, no soft-delete)
    // -----------------------------------------------------------------
    private function createOfferApprovals(Database $db): void
    {
        if ($this->hasTable($db, 'offer_approvals')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `offer_approvals` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `workspace_id` BIGINT UNSIGNED NOT NULL,
                `offer_id` BIGINT UNSIGNED NOT NULL,
                `approval_id` BIGINT UNSIGNED NOT NULL,
                `is_current` TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `offer_approvals_uuid_unique` (`uuid`),
                UNIQUE KEY `offer_approvals_offer_approval_unique` (`offer_id`, `approval_id`),
                KEY `offer_approvals_workspace_id_index` (`workspace_id`),
                KEY `offer_approvals_offer_id_index` (`offer_id`),
                KEY `offer_approvals_approval_id_index` (`approval_id`),
                KEY `offer_approvals_offer_current_index` (`offer_id`, `is_current`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    // -----------------------------------------------------------------
    // 15. approvals (generic polymorphic workflow)
    // -----------------------------------------------------------------
    private function createApprovals(Database $db): void
    {
        if ($this->hasTable($db, 'approvals')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `approvals` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `workspace_id` BIGINT UNSIGNED NOT NULL,
                `approvable_type` VARCHAR(60) NOT NULL,
                `approvable_id` BIGINT UNSIGNED NOT NULL,
                `title` VARCHAR(200) NULL,
                `status_id` BIGINT UNSIGNED NULL,
                `mode_id` BIGINT UNSIGNED NULL,
                `current_step` INT UNSIGNED NOT NULL DEFAULT 1,
                `requested_by` BIGINT UNSIGNED NULL,
                `decided_at` TIMESTAMP NULL DEFAULT NULL,
                `due_at` DATETIME NULL,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                `deleted_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `approvals_uuid_unique` (`uuid`),
                KEY `approvals_workspace_id_index` (`workspace_id`),
                KEY `approvals_status_id_index` (`status_id`),
                KEY `approvals_mode_id_index` (`mode_id`),
                KEY `approvals_requested_by_index` (`requested_by`),
                KEY `approvals_approvable_index` (`approvable_type`, `approvable_id`),
                KEY `approvals_workspace_status_index` (`workspace_id`, `status_id`),
                KEY `approvals_deleted_at_index` (`deleted_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    // -----------------------------------------------------------------
    // 16. approval_steps (child, no soft-delete)
    // -----------------------------------------------------------------
    private function createApprovalSteps(Database $db): void
    {
        if ($this->hasTable($db, 'approval_steps')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `approval_steps` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `workspace_id` BIGINT UNSIGNED NOT NULL,
                `approval_id` BIGINT UNSIGNED NOT NULL,
                `step_order` INT UNSIGNED NOT NULL DEFAULT 1,
                `approver_id` BIGINT UNSIGNED NULL,
                `approver_role_id` BIGINT UNSIGNED NULL,
                `status_id` BIGINT UNSIGNED NULL,
                `comment` TEXT NULL,
                `acted_at` TIMESTAMP NULL DEFAULT NULL,
                `is_required` TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `approval_steps_uuid_unique` (`uuid`),
                UNIQUE KEY `approval_steps_approval_order_unique` (`approval_id`, `step_order`),
                KEY `approval_steps_workspace_id_index` (`workspace_id`),
                KEY `approval_steps_approval_id_index` (`approval_id`),
                KEY `approval_steps_approver_id_index` (`approver_id`),
                KEY `approval_steps_approver_role_id_index` (`approver_role_id`),
                KEY `approval_steps_status_id_index` (`status_id`),
                KEY `approval_steps_approver_status_index` (`approver_id`, `status_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    // -----------------------------------------------------------------
    // 17. pools
    // -----------------------------------------------------------------
    private function createPools(Database $db): void
    {
        if ($this->hasTable($db, 'pools')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `pools` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `workspace_id` BIGINT UNSIGNED NOT NULL,
                `name` VARCHAR(150) NOT NULL,
                `slug` VARCHAR(160) NOT NULL,
                `description` TEXT NULL,
                `type_id` BIGINT UNSIGNED NULL,
                `owner_user_id` BIGINT UNSIGNED NULL,
                `department_id` BIGINT UNSIGNED NULL,
                `is_shared` TINYINT(1) NOT NULL DEFAULT 0,
                `candidate_count` INT UNSIGNED NOT NULL DEFAULT 0,
                `created_by` BIGINT UNSIGNED NULL,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                `deleted_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `pools_uuid_unique` (`uuid`),
                UNIQUE KEY `pools_workspace_slug_unique` (`workspace_id`, `slug`),
                KEY `pools_workspace_id_index` (`workspace_id`),
                KEY `pools_type_id_index` (`type_id`),
                KEY `pools_owner_user_id_index` (`owner_user_id`),
                KEY `pools_department_id_index` (`department_id`),
                KEY `pools_created_by_index` (`created_by`),
                KEY `pools_workspace_type_index` (`workspace_id`, `type_id`),
                KEY `pools_deleted_at_index` (`deleted_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    // -----------------------------------------------------------------
    // 18. pool_groups
    // -----------------------------------------------------------------
    private function createPoolGroups(Database $db): void
    {
        if ($this->hasTable($db, 'pool_groups')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `pool_groups` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `workspace_id` BIGINT UNSIGNED NOT NULL,
                `pool_id` BIGINT UNSIGNED NOT NULL,
                `name` VARCHAR(150) NOT NULL,
                `slug` VARCHAR(160) NOT NULL,
                `description` TEXT NULL,
                `color` VARCHAR(20) NULL,
                `sort_order` INT NOT NULL DEFAULT 0,
                `created_by` BIGINT UNSIGNED NULL,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                `deleted_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `pool_groups_uuid_unique` (`uuid`),
                UNIQUE KEY `pool_groups_pool_slug_unique` (`pool_id`, `slug`),
                KEY `pool_groups_workspace_id_index` (`workspace_id`),
                KEY `pool_groups_pool_id_index` (`pool_id`),
                KEY `pool_groups_created_by_index` (`created_by`),
                KEY `pool_groups_pool_sort_index` (`pool_id`, `sort_order`),
                KEY `pool_groups_deleted_at_index` (`deleted_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    // -----------------------------------------------------------------
    // 19. pool_candidates
    // -----------------------------------------------------------------
    private function createPoolCandidates(Database $db): void
    {
        if ($this->hasTable($db, 'pool_candidates')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `pool_candidates` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `workspace_id` BIGINT UNSIGNED NOT NULL,
                `pool_id` BIGINT UNSIGNED NOT NULL,
                `pool_group_id` BIGINT UNSIGNED NULL,
                `user_id` BIGINT UNSIGNED NOT NULL,
                `source_id` BIGINT UNSIGNED NULL,
                `stage_id` BIGINT UNSIGNED NULL,
                `added_by` BIGINT UNSIGNED NULL,
                `added_at` TIMESTAMP NULL DEFAULT NULL,
                `last_contacted_at` TIMESTAMP NULL DEFAULT NULL,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                `deleted_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `pool_candidates_uuid_unique` (`uuid`),
                UNIQUE KEY `pool_candidates_pool_user_unique` (`pool_id`, `user_id`),
                KEY `pool_candidates_workspace_id_index` (`workspace_id`),
                KEY `pool_candidates_pool_id_index` (`pool_id`),
                KEY `pool_candidates_pool_group_id_index` (`pool_group_id`),
                KEY `pool_candidates_user_id_index` (`user_id`),
                KEY `pool_candidates_source_id_index` (`source_id`),
                KEY `pool_candidates_stage_id_index` (`stage_id`),
                KEY `pool_candidates_added_by_index` (`added_by`),
                KEY `pool_candidates_workspace_pool_index` (`workspace_id`, `pool_id`),
                KEY `pool_candidates_deleted_at_index` (`deleted_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    // -----------------------------------------------------------------
    // Seeds: offer_statuses system defaults (workspace_id NULL, is_system=1)
    // -----------------------------------------------------------------
    private function seedOfferStatuses(Database $db): void
    {
        if (! $this->hasTable($db, 'offer_statuses')) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        foreach ($this->offerStatuses as [$key, $label, $color, $sort, $isDefault, $isInitial, $isTerminal]) {
            $exists = $db->table('offer_statuses')
                ->whereNull('workspace_id')
                ->where('key', '=', $key)
                ->exists();
            if ($exists) {
                continue;
            }
            $db->table('offer_statuses')->insert([
                'uuid'        => $this->uuid($db),
                'workspace_id' => null,
                'key'         => $key,
                'label'       => $label,
                'color'       => $color,
                'sort_order'  => $sort,
                'is_default'  => $isDefault,
                'is_initial'  => $isInitial,
                'is_terminal' => $isTerminal,
                'is_system'   => 1,
                'created_at'  => $now,
                'updated_at'  => $now,
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
