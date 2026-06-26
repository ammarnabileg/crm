<?php

declare(strict_types=1);

use App\Core\Database;
use Database\Migration;

/**
 * D9 — HR & Talent Pool: FOREIGN KEYS only (companion to 0027_create_hr_talent.php).
 *
 * Adds every foreign key for the D9 tables now that all anchor, intra-domain and
 * cross-domain tables exist: anchors (`workspaces`, `users`, `currencies`,
 * `timezones`, `lookup_values`), intra-domain (departments self-ref, teams,
 * interview_panels, schedules, evaluation_forms/fields/evaluations, offers/
 * offer_statuses/offer_approvals, approvals/approval_steps, pools/pool_groups),
 * and cross-domain D5/D7 (`jobs` is the *reverse* edge — `jobs.department_id`
 * → departments, owned by D5; here we reference `applications` and `interviews`
 * from D7).
 *
 * Each ADD/DROP CONSTRAINT is guarded with hasConstraint() so the migration is
 * idempotent. Per the polymorphic policy, NO FK is added on
 * `approvals.(approvable_type, approvable_id)` — that edge is indexed and
 * app-enforced.
 */
return new class extends Migration {
    /**
     * Full FK map: table => [ [constraint, column, ref_table, on_delete, on_update], ... ]
     */
    private function foreignKeys(): array
    {
        return [
            'departments' => [
                ['departments_workspace_id_foreign', 'workspace_id', 'workspaces', 'CASCADE', 'CASCADE'],
                ['departments_parent_id_foreign', 'parent_id', 'departments', 'SET NULL', 'CASCADE'],
                ['departments_head_user_id_foreign', 'head_user_id', 'users', 'SET NULL', 'CASCADE'],
                ['departments_created_by_foreign', 'created_by', 'users', 'SET NULL', 'CASCADE'],
            ],
            'teams' => [
                ['teams_workspace_id_foreign', 'workspace_id', 'workspaces', 'CASCADE', 'CASCADE'],
                ['teams_department_id_foreign', 'department_id', 'departments', 'SET NULL', 'CASCADE'],
                ['teams_lead_user_id_foreign', 'lead_user_id', 'users', 'SET NULL', 'CASCADE'],
                ['teams_created_by_foreign', 'created_by', 'users', 'SET NULL', 'CASCADE'],
            ],
            'team_members' => [
                ['team_members_workspace_id_foreign', 'workspace_id', 'workspaces', 'CASCADE', 'CASCADE'],
                ['team_members_team_id_foreign', 'team_id', 'teams', 'CASCADE', 'CASCADE'],
                ['team_members_user_id_foreign', 'user_id', 'users', 'CASCADE', 'CASCADE'],
                ['team_members_role_id_foreign', 'role_id', 'lookup_values', 'RESTRICT', 'CASCADE'],
            ],
            'interview_panels' => [
                ['interview_panels_workspace_id_foreign', 'workspace_id', 'workspaces', 'CASCADE', 'CASCADE'],
                ['interview_panels_interview_id_foreign', 'interview_id', 'interviews', 'CASCADE', 'CASCADE'],
                ['interview_panels_chair_user_id_foreign', 'chair_user_id', 'users', 'SET NULL', 'CASCADE'],
                ['interview_panels_created_by_foreign', 'created_by', 'users', 'SET NULL', 'CASCADE'],
            ],
            'panel_members' => [
                ['panel_members_workspace_id_foreign', 'workspace_id', 'workspaces', 'CASCADE', 'CASCADE'],
                ['panel_members_panel_id_foreign', 'panel_id', 'interview_panels', 'CASCADE', 'CASCADE'],
                ['panel_members_user_id_foreign', 'user_id', 'users', 'CASCADE', 'CASCADE'],
                ['panel_members_role_id_foreign', 'role_id', 'lookup_values', 'RESTRICT', 'CASCADE'],
            ],
            'schedules' => [
                ['schedules_workspace_id_foreign', 'workspace_id', 'workspaces', 'CASCADE', 'CASCADE'],
                ['schedules_owner_user_id_foreign', 'owner_user_id', 'users', 'SET NULL', 'CASCADE'],
                ['schedules_team_id_foreign', 'team_id', 'teams', 'SET NULL', 'CASCADE'],
                ['schedules_timezone_id_foreign', 'timezone_id', 'timezones', 'RESTRICT', 'CASCADE'],
                ['schedules_visibility_id_foreign', 'visibility_id', 'lookup_values', 'RESTRICT', 'CASCADE'],
                ['schedules_created_by_foreign', 'created_by', 'users', 'SET NULL', 'CASCADE'],
            ],
            'meetings' => [
                ['meetings_workspace_id_foreign', 'workspace_id', 'workspaces', 'CASCADE', 'CASCADE'],
                ['meetings_schedule_id_foreign', 'schedule_id', 'schedules', 'SET NULL', 'CASCADE'],
                ['meetings_interview_id_foreign', 'interview_id', 'interviews', 'CASCADE', 'CASCADE'],
                ['meetings_organizer_user_id_foreign', 'organizer_user_id', 'users', 'SET NULL', 'CASCADE'],
                ['meetings_type_id_foreign', 'type_id', 'lookup_values', 'RESTRICT', 'CASCADE'],
                ['meetings_status_id_foreign', 'status_id', 'lookup_values', 'RESTRICT', 'CASCADE'],
                ['meetings_location_type_id_foreign', 'location_type_id', 'lookup_values', 'RESTRICT', 'CASCADE'],
                ['meetings_meeting_provider_id_foreign', 'meeting_provider_id', 'lookup_values', 'RESTRICT', 'CASCADE'],
                ['meetings_timezone_id_foreign', 'timezone_id', 'timezones', 'RESTRICT', 'CASCADE'],
                ['meetings_created_by_foreign', 'created_by', 'users', 'SET NULL', 'CASCADE'],
            ],
            'evaluation_forms' => [
                ['evaluation_forms_workspace_id_foreign', 'workspace_id', 'workspaces', 'CASCADE', 'CASCADE'],
                ['evaluation_forms_scope_id_foreign', 'scope_id', 'lookup_values', 'RESTRICT', 'CASCADE'],
                ['evaluation_forms_scoring_type_id_foreign', 'scoring_type_id', 'lookup_values', 'RESTRICT', 'CASCADE'],
                ['evaluation_forms_created_by_foreign', 'created_by', 'users', 'SET NULL', 'CASCADE'],
            ],
            'evaluation_form_fields' => [
                ['evaluation_form_fields_workspace_id_foreign', 'workspace_id', 'workspaces', 'CASCADE', 'CASCADE'],
                ['evaluation_form_fields_form_id_foreign', 'form_id', 'evaluation_forms', 'CASCADE', 'CASCADE'],
                ['evaluation_form_fields_field_type_id_foreign', 'field_type_id', 'lookup_values', 'RESTRICT', 'CASCADE'],
                ['evaluation_form_fields_category_id_foreign', 'category_id', 'lookup_values', 'RESTRICT', 'CASCADE'],
            ],
            'evaluations' => [
                ['evaluations_workspace_id_foreign', 'workspace_id', 'workspaces', 'CASCADE', 'CASCADE'],
                ['evaluations_application_id_foreign', 'application_id', 'applications', 'CASCADE', 'CASCADE'],
                ['evaluations_interview_id_foreign', 'interview_id', 'interviews', 'SET NULL', 'CASCADE'],
                ['evaluations_form_id_foreign', 'form_id', 'evaluation_forms', 'RESTRICT', 'CASCADE'],
                ['evaluations_evaluator_id_foreign', 'evaluator_id', 'users', 'SET NULL', 'CASCADE'],
                ['evaluations_recommendation_id_foreign', 'recommendation_id', 'lookup_values', 'RESTRICT', 'CASCADE'],
                ['evaluations_created_by_foreign', 'created_by', 'users', 'SET NULL', 'CASCADE'],
            ],
            'evaluation_scores' => [
                ['evaluation_scores_workspace_id_foreign', 'workspace_id', 'workspaces', 'CASCADE', 'CASCADE'],
                ['evaluation_scores_evaluation_id_foreign', 'evaluation_id', 'evaluations', 'CASCADE', 'CASCADE'],
                ['evaluation_scores_field_id_foreign', 'field_id', 'evaluation_form_fields', 'RESTRICT', 'CASCADE'],
                ['evaluation_scores_value_option_id_foreign', 'value_option_id', 'lookup_values', 'RESTRICT', 'CASCADE'],
            ],
            'offer_statuses' => [
                ['offer_statuses_workspace_id_foreign', 'workspace_id', 'workspaces', 'CASCADE', 'CASCADE'],
            ],
            'offers' => [
                ['offers_workspace_id_foreign', 'workspace_id', 'workspaces', 'CASCADE', 'CASCADE'],
                ['offers_application_id_foreign', 'application_id', 'applications', 'CASCADE', 'CASCADE'],
                ['offers_offer_status_id_foreign', 'offer_status_id', 'offer_statuses', 'RESTRICT', 'CASCADE'],
                ['offers_candidate_user_id_foreign', 'candidate_user_id', 'users', 'SET NULL', 'CASCADE'],
                ['offers_employment_type_id_foreign', 'employment_type_id', 'lookup_values', 'RESTRICT', 'CASCADE'],
                ['offers_currency_id_foreign', 'currency_id', 'currencies', 'RESTRICT', 'CASCADE'],
                ['offers_salary_period_id_foreign', 'salary_period_id', 'lookup_values', 'RESTRICT', 'CASCADE'],
                ['offers_created_by_foreign', 'created_by', 'users', 'SET NULL', 'CASCADE'],
            ],
            'offer_approvals' => [
                ['offer_approvals_workspace_id_foreign', 'workspace_id', 'workspaces', 'CASCADE', 'CASCADE'],
                ['offer_approvals_offer_id_foreign', 'offer_id', 'offers', 'CASCADE', 'CASCADE'],
                ['offer_approvals_approval_id_foreign', 'approval_id', 'approvals', 'CASCADE', 'CASCADE'],
            ],
            'approvals' => [
                ['approvals_workspace_id_foreign', 'workspace_id', 'workspaces', 'CASCADE', 'CASCADE'],
                ['approvals_status_id_foreign', 'status_id', 'lookup_values', 'RESTRICT', 'CASCADE'],
                ['approvals_mode_id_foreign', 'mode_id', 'lookup_values', 'RESTRICT', 'CASCADE'],
                ['approvals_requested_by_foreign', 'requested_by', 'users', 'SET NULL', 'CASCADE'],
                // NOTE: no FK on (approvable_type, approvable_id) — polymorphic, app-enforced.
            ],
            'approval_steps' => [
                ['approval_steps_workspace_id_foreign', 'workspace_id', 'workspaces', 'CASCADE', 'CASCADE'],
                ['approval_steps_approval_id_foreign', 'approval_id', 'approvals', 'CASCADE', 'CASCADE'],
                ['approval_steps_approver_id_foreign', 'approver_id', 'users', 'SET NULL', 'CASCADE'],
                ['approval_steps_approver_role_id_foreign', 'approver_role_id', 'lookup_values', 'RESTRICT', 'CASCADE'],
                ['approval_steps_status_id_foreign', 'status_id', 'lookup_values', 'RESTRICT', 'CASCADE'],
            ],
            'pools' => [
                ['pools_workspace_id_foreign', 'workspace_id', 'workspaces', 'CASCADE', 'CASCADE'],
                ['pools_type_id_foreign', 'type_id', 'lookup_values', 'RESTRICT', 'CASCADE'],
                ['pools_owner_user_id_foreign', 'owner_user_id', 'users', 'SET NULL', 'CASCADE'],
                ['pools_department_id_foreign', 'department_id', 'departments', 'SET NULL', 'CASCADE'],
                ['pools_created_by_foreign', 'created_by', 'users', 'SET NULL', 'CASCADE'],
            ],
            'pool_groups' => [
                ['pool_groups_workspace_id_foreign', 'workspace_id', 'workspaces', 'CASCADE', 'CASCADE'],
                ['pool_groups_pool_id_foreign', 'pool_id', 'pools', 'CASCADE', 'CASCADE'],
                ['pool_groups_created_by_foreign', 'created_by', 'users', 'SET NULL', 'CASCADE'],
            ],
            'pool_candidates' => [
                ['pool_candidates_workspace_id_foreign', 'workspace_id', 'workspaces', 'CASCADE', 'CASCADE'],
                ['pool_candidates_pool_id_foreign', 'pool_id', 'pools', 'CASCADE', 'CASCADE'],
                ['pool_candidates_pool_group_id_foreign', 'pool_group_id', 'pool_groups', 'SET NULL', 'CASCADE'],
                ['pool_candidates_user_id_foreign', 'user_id', 'users', 'CASCADE', 'CASCADE'],
                ['pool_candidates_source_id_foreign', 'source_id', 'lookup_values', 'RESTRICT', 'CASCADE'],
                ['pool_candidates_stage_id_foreign', 'stage_id', 'lookup_values', 'RESTRICT', 'CASCADE'],
                ['pool_candidates_added_by_foreign', 'added_by', 'users', 'SET NULL', 'CASCADE'],
            ],
        ];
    }

    public function up(Database $db): void
    {
        foreach ($this->foreignKeys() as $table => $fks) {
            if (! $this->hasTable($db, $table)) {
                continue;
            }
            foreach ($fks as [$name, $column, $refTable, $onDelete, $onUpdate]) {
                if ($this->hasConstraint($db, $table, $name)) {
                    continue;
                }
                $db->unprepared(
                    "ALTER TABLE `{$table}` ADD CONSTRAINT `{$name}` "
                    . "FOREIGN KEY (`{$column}`) REFERENCES `{$refTable}` (`id`) "
                    . "ON DELETE {$onDelete} ON UPDATE {$onUpdate}"
                );
            }
        }
    }

    public function down(Database $db): void
    {
        foreach ($this->foreignKeys() as $table => $fks) {
            if (! $this->hasTable($db, $table)) {
                continue;
            }
            foreach ($fks as [$name, $column, $refTable, $onDelete, $onUpdate]) {
                if ($this->hasConstraint($db, $table, $name)) {
                    $db->unprepared("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$name}`");
                }
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
