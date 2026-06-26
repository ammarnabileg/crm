<?php

declare(strict_types=1);

use App\Core\Database;
use Database\Migration;

/**
 * D7 — Applications & Interviews: FOREIGN KEYS (companion to
 * 0025_create_applications_interviews.php).
 *
 * Adds every foreign key for the domain AFTER all tables (here and in the other
 * domains) exist — the two-file pattern that eliminates create-order and
 * cross-domain cycle problems. Each constraint is guarded by `hasConstraint`
 * for idempotency.
 *
 * Targets:
 *  - Anchors / built tables: `workspaces`, `users`, `timezones`, `lookup_values`.
 *  - Cross-domain: `jobs`, `pipeline_stages`, `job_criteria` (D5), `files` (D10),
 *    `ai_providers`, `ai_models` (D8).
 *  - Intra-domain: `applications`, `application_statuses`, `interviews`,
 *    `interview_statuses` (implicit via interviews), `interview_sessions`,
 *    `interview_questions`, `interview_media`.
 *
 * The two billions-row append tables (`interview_messages`, `interview_logs`)
 * are FK-light by design (Bible §4 scale exception) and intentionally get NO
 * foreign keys — referential integrity is enforced at the application layer.
 *
 * Delete/update rules follow docs/database/08 exactly: owned parents CASCADE;
 * status / lookup / criterion catalog refs RESTRICT; optional actor / file /
 * stage / timezone / provider / model refs SET NULL. ON UPDATE CASCADE
 * throughout.
 */
return new class extends Migration {
    /**
     * Each entry: [table, constraint, column, refTable, refColumn, onDelete, onUpdate].
     */
    private function foreignKeys(): array
    {
        return [
            // applications
            ['applications', 'applications_workspace_id_foreign', 'workspace_id', 'workspaces', 'id', 'CASCADE', 'CASCADE'],
            ['applications', 'applications_job_id_foreign', 'job_id', 'jobs', 'id', 'CASCADE', 'CASCADE'],
            ['applications', 'applications_user_id_foreign', 'user_id', 'users', 'id', 'CASCADE', 'CASCADE'],
            ['applications', 'applications_current_stage_id_foreign', 'current_stage_id', 'pipeline_stages', 'id', 'SET NULL', 'CASCADE'],
            ['applications', 'applications_application_status_id_foreign', 'application_status_id', 'application_statuses', 'id', 'RESTRICT', 'CASCADE'],
            ['applications', 'applications_source_id_foreign', 'source_id', 'lookup_values', 'id', 'RESTRICT', 'CASCADE'],
            ['applications', 'applications_resume_file_id_foreign', 'resume_file_id', 'files', 'id', 'SET NULL', 'CASCADE'],

            // application_statuses
            ['application_statuses', 'application_statuses_workspace_id_foreign', 'workspace_id', 'workspaces', 'id', 'CASCADE', 'CASCADE'],

            // application_decisions
            ['application_decisions', 'application_decisions_workspace_id_foreign', 'workspace_id', 'workspaces', 'id', 'CASCADE', 'CASCADE'],
            ['application_decisions', 'application_decisions_application_id_foreign', 'application_id', 'applications', 'id', 'CASCADE', 'CASCADE'],
            ['application_decisions', 'application_decisions_decision_id_foreign', 'decision_id', 'lookup_values', 'id', 'RESTRICT', 'CASCADE'],
            ['application_decisions', 'application_decisions_from_status_id_foreign', 'from_status_id', 'application_statuses', 'id', 'RESTRICT', 'CASCADE'],
            ['application_decisions', 'application_decisions_to_status_id_foreign', 'to_status_id', 'application_statuses', 'id', 'RESTRICT', 'CASCADE'],
            ['application_decisions', 'application_decisions_decided_by_foreign', 'decided_by', 'users', 'id', 'SET NULL', 'CASCADE'],

            // application_ai_results
            ['application_ai_results', 'application_ai_results_workspace_id_foreign', 'workspace_id', 'workspaces', 'id', 'CASCADE', 'CASCADE'],
            ['application_ai_results', 'application_ai_results_application_id_foreign', 'application_id', 'applications', 'id', 'CASCADE', 'CASCADE'],
            ['application_ai_results', 'application_ai_results_provider_id_foreign', 'provider_id', 'ai_providers', 'id', 'SET NULL', 'CASCADE'],
            ['application_ai_results', 'application_ai_results_model_id_foreign', 'model_id', 'ai_models', 'id', 'SET NULL', 'CASCADE'],

            // interviews
            ['interviews', 'interviews_workspace_id_foreign', 'workspace_id', 'workspaces', 'id', 'CASCADE', 'CASCADE'],
            ['interviews', 'interviews_application_id_foreign', 'application_id', 'applications', 'id', 'CASCADE', 'CASCADE'],
            ['interviews', 'interviews_job_id_foreign', 'job_id', 'jobs', 'id', 'CASCADE', 'CASCADE'],
            ['interviews', 'interviews_type_id_foreign', 'type_id', 'lookup_values', 'id', 'RESTRICT', 'CASCADE'],
            ['interviews', 'interviews_mode_id_foreign', 'mode_id', 'lookup_values', 'id', 'RESTRICT', 'CASCADE'],
            ['interviews', 'interviews_interview_status_id_foreign', 'interview_status_id', 'interview_statuses', 'id', 'RESTRICT', 'CASCADE'],
            ['interviews', 'interviews_timezone_id_foreign', 'timezone_id', 'timezones', 'id', 'SET NULL', 'CASCADE'],
            ['interviews', 'interviews_created_by_foreign', 'created_by', 'users', 'id', 'SET NULL', 'CASCADE'],

            // interview_statuses
            ['interview_statuses', 'interview_statuses_workspace_id_foreign', 'workspace_id', 'workspaces', 'id', 'CASCADE', 'CASCADE'],

            // interview_sessions
            ['interview_sessions', 'interview_sessions_workspace_id_foreign', 'workspace_id', 'workspaces', 'id', 'CASCADE', 'CASCADE'],
            ['interview_sessions', 'interview_sessions_interview_id_foreign', 'interview_id', 'interviews', 'id', 'CASCADE', 'CASCADE'],
            ['interview_sessions', 'interview_sessions_session_status_id_foreign', 'session_status_id', 'lookup_values', 'id', 'RESTRICT', 'CASCADE'],
            ['interview_sessions', 'interview_sessions_started_by_foreign', 'started_by', 'users', 'id', 'SET NULL', 'CASCADE'],

            // interview_media
            ['interview_media', 'interview_media_workspace_id_foreign', 'workspace_id', 'workspaces', 'id', 'CASCADE', 'CASCADE'],
            ['interview_media', 'interview_media_interview_id_foreign', 'interview_id', 'interviews', 'id', 'CASCADE', 'CASCADE'],
            ['interview_media', 'interview_media_session_id_foreign', 'session_id', 'interview_sessions', 'id', 'SET NULL', 'CASCADE'],
            ['interview_media', 'interview_media_question_id_foreign', 'question_id', 'interview_questions', 'id', 'SET NULL', 'CASCADE'],
            ['interview_media', 'interview_media_type_id_foreign', 'type_id', 'lookup_values', 'id', 'RESTRICT', 'CASCADE'],
            ['interview_media', 'interview_media_file_id_foreign', 'file_id', 'files', 'id', 'SET NULL', 'CASCADE'],

            // interview_questions
            ['interview_questions', 'interview_questions_workspace_id_foreign', 'workspace_id', 'workspaces', 'id', 'CASCADE', 'CASCADE'],
            ['interview_questions', 'interview_questions_interview_id_foreign', 'interview_id', 'interviews', 'id', 'CASCADE', 'CASCADE'],
            ['interview_questions', 'interview_questions_job_id_foreign', 'job_id', 'jobs', 'id', 'CASCADE', 'CASCADE'],
            ['interview_questions', 'interview_questions_type_id_foreign', 'type_id', 'lookup_values', 'id', 'RESTRICT', 'CASCADE'],
            ['interview_questions', 'interview_questions_criterion_id_foreign', 'criterion_id', 'job_criteria', 'id', 'SET NULL', 'CASCADE'],

            // interview_answers
            ['interview_answers', 'interview_answers_workspace_id_foreign', 'workspace_id', 'workspaces', 'id', 'CASCADE', 'CASCADE'],
            ['interview_answers', 'interview_answers_interview_id_foreign', 'interview_id', 'interviews', 'id', 'CASCADE', 'CASCADE'],
            ['interview_answers', 'interview_answers_session_id_foreign', 'session_id', 'interview_sessions', 'id', 'SET NULL', 'CASCADE'],
            ['interview_answers', 'interview_answers_question_id_foreign', 'question_id', 'interview_questions', 'id', 'CASCADE', 'CASCADE'],
            ['interview_answers', 'interview_answers_user_id_foreign', 'user_id', 'users', 'id', 'CASCADE', 'CASCADE'],
            ['interview_answers', 'interview_answers_media_id_foreign', 'media_id', 'interview_media', 'id', 'SET NULL', 'CASCADE'],
            ['interview_answers', 'interview_answers_answer_file_id_foreign', 'answer_file_id', 'files', 'id', 'SET NULL', 'CASCADE'],

            // interview_scores
            ['interview_scores', 'interview_scores_workspace_id_foreign', 'workspace_id', 'workspaces', 'id', 'CASCADE', 'CASCADE'],
            ['interview_scores', 'interview_scores_interview_id_foreign', 'interview_id', 'interviews', 'id', 'CASCADE', 'CASCADE'],
            ['interview_scores', 'interview_scores_session_id_foreign', 'session_id', 'interview_sessions', 'id', 'SET NULL', 'CASCADE'],
            ['interview_scores', 'interview_scores_criterion_id_foreign', 'criterion_id', 'job_criteria', 'id', 'RESTRICT', 'CASCADE'],
            ['interview_scores', 'interview_scores_scored_by_foreign', 'scored_by', 'users', 'id', 'SET NULL', 'CASCADE'],

            // interview_ai_analyses
            ['interview_ai_analyses', 'interview_ai_analyses_workspace_id_foreign', 'workspace_id', 'workspaces', 'id', 'CASCADE', 'CASCADE'],
            ['interview_ai_analyses', 'interview_ai_analyses_interview_id_foreign', 'interview_id', 'interviews', 'id', 'CASCADE', 'CASCADE'],
            ['interview_ai_analyses', 'interview_ai_analyses_session_id_foreign', 'session_id', 'interview_sessions', 'id', 'SET NULL', 'CASCADE'],
            ['interview_ai_analyses', 'interview_ai_analyses_provider_id_foreign', 'provider_id', 'ai_providers', 'id', 'SET NULL', 'CASCADE'],
            ['interview_ai_analyses', 'interview_ai_analyses_model_id_foreign', 'model_id', 'ai_models', 'id', 'SET NULL', 'CASCADE'],

            // interview_tokens
            ['interview_tokens', 'interview_tokens_workspace_id_foreign', 'workspace_id', 'workspaces', 'id', 'CASCADE', 'CASCADE'],
            ['interview_tokens', 'interview_tokens_interview_id_foreign', 'interview_id', 'interviews', 'id', 'CASCADE', 'CASCADE'],
            ['interview_tokens', 'interview_tokens_user_id_foreign', 'user_id', 'users', 'id', 'CASCADE', 'CASCADE'],

            // interview_participants
            ['interview_participants', 'interview_participants_workspace_id_foreign', 'workspace_id', 'workspaces', 'id', 'CASCADE', 'CASCADE'],
            ['interview_participants', 'interview_participants_interview_id_foreign', 'interview_id', 'interviews', 'id', 'CASCADE', 'CASCADE'],
            ['interview_participants', 'interview_participants_user_id_foreign', 'user_id', 'users', 'id', 'CASCADE', 'CASCADE'],
            ['interview_participants', 'interview_participants_role_id_foreign', 'role_id', 'lookup_values', 'id', 'RESTRICT', 'CASCADE'],
            ['interview_participants', 'interview_participants_response_id_foreign', 'response_id', 'lookup_values', 'id', 'RESTRICT', 'CASCADE'],
        ];
    }

    public function up(Database $db): void
    {
        foreach ($this->foreignKeys() as [$table, $constraint, $column, $refTable, $refColumn, $onDelete, $onUpdate]) {
            if (! $this->hasTable($db, $table)) {
                continue;
            }
            if ($this->hasConstraint($db, $table, $constraint)) {
                continue;
            }
            $db->unprepared(
                "ALTER TABLE `{$table}` ADD CONSTRAINT `{$constraint}` FOREIGN KEY (`{$column}`) "
                . "REFERENCES `{$refTable}` (`{$refColumn}`) ON DELETE {$onDelete} ON UPDATE {$onUpdate}"
            );
        }
    }

    public function down(Database $db): void
    {
        foreach ($this->foreignKeys() as [$table, $constraint]) {
            if ($this->hasConstraint($db, $table, $constraint)) {
                $db->unprepared("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$constraint}`");
            }
        }
    }

    // ------------------------------------------------------------------ idempotency

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
