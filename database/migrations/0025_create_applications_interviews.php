<?php

declare(strict_types=1);

use App\Core\Database;
use Database\Migration;

/**
 * D7 — Applications & Interviews (docs/database/08-Applications-Interviews.md).
 *
 * The hiring core: the `applications` object that links a candidate (`users`
 * row) to a `jobs` row, its status/decision/AI-result satellites, and the full
 * interview subsystem — scheduling (`interviews`), participants, per-run
 * `interview_sessions`, the high-volume `interview_messages` stream, captured
 * `interview_media`/`interview_questions`/`interview_answers`, per-criterion
 * `interview_scores`, `interview_ai_analyses`, secure `interview_tokens`, and the
 * high-volume `interview_logs` audit stream.
 *
 * STRUCTURE ONLY — this file creates every table with all PK/UNIQUE/business
 * indexes plus an index on every FK column, but NO foreign key constraints
 * (those live in 0125_fk_applications_interviews.php, the two-file pattern that
 * removes create-order / cross-domain cycle problems). It then seeds the
 * system-default rows for the two configuration-driven status catalogs.
 *
 * Configuration-driven (no ENUMs): per-entity `application_statuses` /
 * `interview_statuses` tables, plus `lookup_values` FKs for source / interview
 * type / mode / participant role+response / media type / question type /
 * answer-ish types / session status / decision.
 *
 * Scale: `interview_messages` and `interview_logs` are billions-row append-only
 * tables — narrow, FK-light, `uuid` omitted, composite PK `(id, created_at)` so
 * the partition key is in the PK. They are partition-READY (RANGE on
 * `created_at`); the physical PARTITION is applied later as an ops step (InnoDB
 * FKs and partitions conflict). Idempotent via information_schema guards.
 */
return new class extends Migration {
    /** application_statuses system defaults: [key, label, color, sort, is_default, is_initial, is_terminal]. */
    private array $applicationStatuses = [
        ['applied',      'Applied',      '#3b82f6', 1, 1, 1, 0],
        ['in_review',    'In Review',    '#6366f1', 2, 0, 0, 0],
        ['interviewing', 'Interviewing', '#8b5cf6', 3, 0, 0, 0],
        ['offer',        'Offer',        '#f59e0b', 4, 0, 0, 0],
        ['hired',        'Hired',        '#22c55e', 5, 0, 0, 1],
        ['rejected',     'Rejected',     '#ef4444', 6, 0, 0, 1],
        ['withdrawn',    'Withdrawn',    '#6b7280', 7, 0, 0, 1],
    ];

    /** interview_statuses system defaults: [key, label, color, sort, is_default, is_initial, is_terminal]. */
    private array $interviewStatuses = [
        ['scheduled',   'Scheduled',   '#3b82f6', 1, 1, 1, 0],
        ['in_progress', 'In Progress', '#8b5cf6', 2, 0, 0, 0],
        ['completed',   'Completed',   '#22c55e', 3, 0, 0, 1],
        ['canceled',    'Canceled',    '#6b7280', 4, 0, 0, 1],
        ['no_show',     'No Show',     '#ef4444', 5, 0, 0, 1],
    ];

    public function up(Database $db): void
    {
        $this->createApplications($db);
        $this->createApplicationStatuses($db);
        $this->createApplicationDecisions($db);
        $this->createApplicationAiResults($db);
        $this->createInterviews($db);
        $this->createInterviewStatuses($db);
        $this->createInterviewSessions($db);
        $this->createInterviewMessages($db);
        $this->createInterviewMedia($db);
        $this->createInterviewQuestions($db);
        $this->createInterviewAnswers($db);
        $this->createInterviewScores($db);
        $this->createInterviewAiAnalyses($db);
        $this->createInterviewTokens($db);
        $this->createInterviewLogs($db);
        $this->createInterviewParticipants($db);

        // Seed the system-default rows for the two config-driven status catalogs.
        $this->seedApplicationStatuses($db);
        $this->seedInterviewStatuses($db);
    }

    public function down(Database $db): void
    {
        // Drop in reverse dependency order (no FKs in this file, but tidy anyway).
        foreach ([
            'interview_participants',
            'interview_logs',
            'interview_tokens',
            'interview_ai_analyses',
            'interview_scores',
            'interview_answers',
            'interview_questions',
            'interview_media',
            'interview_messages',
            'interview_sessions',
            'interview_statuses',
            'interviews',
            'application_ai_results',
            'application_decisions',
            'application_statuses',
            'applications',
        ] as $table) {
            $db->unprepared("DROP TABLE IF EXISTS `{$table}`");
        }
    }

    // ---------------------------------------------------------------- applications

    private function createApplications(Database $db): void
    {
        if ($this->hasTable($db, 'applications')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `applications` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `workspace_id` BIGINT UNSIGNED NOT NULL,
                `job_id` BIGINT UNSIGNED NOT NULL,
                `user_id` BIGINT UNSIGNED NOT NULL,
                `current_stage_id` BIGINT UNSIGNED NULL,
                `application_status_id` BIGINT UNSIGNED NOT NULL,
                `source_id` BIGINT UNSIGNED NULL,
                `resume_file_id` BIGINT UNSIGNED NULL,
                `cover_letter` TEXT NULL,
                `score` DECIMAL(5,2) NULL,
                `applied_at` TIMESTAMP NULL DEFAULT NULL,
                `decided_at` TIMESTAMP NULL DEFAULT NULL,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                `deleted_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_applications_uuid` (`uuid`),
                UNIQUE KEY `uq_applications_workspace_job_user` (`workspace_id`, `job_id`, `user_id`),
                KEY `ix_applications_workspace_status_stage` (`workspace_id`, `application_status_id`, `current_stage_id`),
                KEY `ix_applications_job` (`job_id`),
                KEY `ix_applications_user` (`user_id`),
                KEY `ix_applications_current_stage` (`current_stage_id`),
                KEY `ix_applications_status` (`application_status_id`),
                KEY `ix_applications_source` (`source_id`),
                KEY `ix_applications_resume_file` (`resume_file_id`),
                KEY `ix_applications_workspace_applied_at` (`workspace_id`, `applied_at`),
                KEY `ix_applications_deleted_at` (`deleted_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    // ----------------------------------------------------------- application_statuses

    private function createApplicationStatuses(Database $db): void
    {
        if ($this->hasTable($db, 'application_statuses')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `application_statuses` (
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
                UNIQUE KEY `uq_application_statuses_uuid` (`uuid`),
                UNIQUE KEY `uq_application_statuses_workspace_key` (`workspace_id`, `key`),
                KEY `ix_application_statuses_workspace_sort` (`workspace_id`, `sort_order`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    // ---------------------------------------------------------- application_decisions

    private function createApplicationDecisions(Database $db): void
    {
        if ($this->hasTable($db, 'application_decisions')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `application_decisions` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `workspace_id` BIGINT UNSIGNED NOT NULL,
                `application_id` BIGINT UNSIGNED NOT NULL,
                `decision_id` BIGINT UNSIGNED NOT NULL,
                `from_status_id` BIGINT UNSIGNED NULL,
                `to_status_id` BIGINT UNSIGNED NULL,
                `decided_by` BIGINT UNSIGNED NULL,
                `reason` TEXT NULL,
                `meta` JSON NULL,
                `decided_at` TIMESTAMP NULL DEFAULT NULL,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_application_decisions_uuid` (`uuid`),
                KEY `ix_application_decisions_application` (`application_id`),
                KEY `ix_application_decisions_workspace` (`workspace_id`),
                KEY `ix_application_decisions_decision` (`decision_id`),
                KEY `ix_application_decisions_decided_by` (`decided_by`),
                KEY `ix_application_decisions_from_status` (`from_status_id`),
                KEY `ix_application_decisions_to_status` (`to_status_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    // ---------------------------------------------------------- application_ai_results

    private function createApplicationAiResults(Database $db): void
    {
        if ($this->hasTable($db, 'application_ai_results')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `application_ai_results` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `workspace_id` BIGINT UNSIGNED NOT NULL,
                `application_id` BIGINT UNSIGNED NOT NULL,
                `overall_score` DECIMAL(5,2) NULL,
                `scores` JSON NULL,
                `analysis` JSON NULL,
                `provider_id` BIGINT UNSIGNED NULL,
                `model_id` BIGINT UNSIGNED NULL,
                `tokens_used` INT UNSIGNED NULL,
                `generated_at` TIMESTAMP NULL DEFAULT NULL,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_application_ai_results_uuid` (`uuid`),
                UNIQUE KEY `uq_application_ai_results_application` (`application_id`),
                KEY `ix_application_ai_results_workspace` (`workspace_id`),
                KEY `ix_application_ai_results_provider` (`provider_id`),
                KEY `ix_application_ai_results_model` (`model_id`),
                KEY `ix_application_ai_results_workspace_score` (`workspace_id`, `overall_score`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    // ------------------------------------------------------------------- interviews

    private function createInterviews(Database $db): void
    {
        if ($this->hasTable($db, 'interviews')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `interviews` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `workspace_id` BIGINT UNSIGNED NOT NULL,
                `application_id` BIGINT UNSIGNED NOT NULL,
                `job_id` BIGINT UNSIGNED NOT NULL,
                `type_id` BIGINT UNSIGNED NOT NULL,
                `mode_id` BIGINT UNSIGNED NOT NULL,
                `interview_status_id` BIGINT UNSIGNED NOT NULL,
                `title` VARCHAR(180) NULL,
                `scheduled_at` TIMESTAMP NULL DEFAULT NULL,
                `duration_minutes` SMALLINT UNSIGNED NULL,
                `location` VARCHAR(255) NULL,
                `meeting_link` VARCHAR(512) NULL,
                `timezone_id` BIGINT UNSIGNED NULL,
                `created_by` BIGINT UNSIGNED NULL,
                `started_at` TIMESTAMP NULL DEFAULT NULL,
                `ended_at` TIMESTAMP NULL DEFAULT NULL,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                `deleted_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_interviews_uuid` (`uuid`),
                KEY `ix_interviews_workspace_application_status` (`workspace_id`, `application_id`, `interview_status_id`),
                KEY `ix_interviews_application` (`application_id`),
                KEY `ix_interviews_job` (`job_id`),
                KEY `ix_interviews_status` (`interview_status_id`),
                KEY `ix_interviews_type` (`type_id`),
                KEY `ix_interviews_mode` (`mode_id`),
                KEY `ix_interviews_timezone` (`timezone_id`),
                KEY `ix_interviews_created_by` (`created_by`),
                KEY `ix_interviews_workspace_scheduled_at` (`workspace_id`, `scheduled_at`),
                KEY `ix_interviews_deleted_at` (`deleted_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    // ------------------------------------------------------------- interview_statuses

    private function createInterviewStatuses(Database $db): void
    {
        if ($this->hasTable($db, 'interview_statuses')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `interview_statuses` (
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
                UNIQUE KEY `uq_interview_statuses_uuid` (`uuid`),
                UNIQUE KEY `uq_interview_statuses_workspace_key` (`workspace_id`, `key`),
                KEY `ix_interview_statuses_workspace_sort` (`workspace_id`, `sort_order`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    // ------------------------------------------------------------- interview_sessions

    private function createInterviewSessions(Database $db): void
    {
        if ($this->hasTable($db, 'interview_sessions')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `interview_sessions` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `workspace_id` BIGINT UNSIGNED NOT NULL,
                `interview_id` BIGINT UNSIGNED NOT NULL,
                `session_status_id` BIGINT UNSIGNED NULL,
                `attempt` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
                `started_by` BIGINT UNSIGNED NULL,
                `is_ai` TINYINT(1) NOT NULL DEFAULT 0,
                `transcript` LONGTEXT NULL,
                `meta` JSON NULL,
                `started_at` TIMESTAMP NULL DEFAULT NULL,
                `ended_at` TIMESTAMP NULL DEFAULT NULL,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_interview_sessions_uuid` (`uuid`),
                UNIQUE KEY `uq_interview_sessions_interview_attempt` (`interview_id`, `attempt`),
                KEY `ix_interview_sessions_workspace` (`workspace_id`),
                KEY `ix_interview_sessions_status` (`session_status_id`),
                KEY `ix_interview_sessions_started_by` (`started_by`),
                KEY `ix_interview_sessions_workspace_started_at` (`workspace_id`, `started_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    // ------------------------------------------------------------- interview_messages
    // scale: partition candidate by RANGE(created_at) — billions-row append-only,
    // FK-light, uuid omitted, composite PK (id, created_at). No physical PARTITION here.

    private function createInterviewMessages(Database $db): void
    {
        if ($this->hasTable($db, 'interview_messages')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `interview_messages` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `workspace_id` BIGINT UNSIGNED NOT NULL,
                `interview_id` BIGINT UNSIGNED NOT NULL,
                `session_id` BIGINT UNSIGNED NOT NULL,
                `sender_type` TINYINT UNSIGNED NOT NULL,
                `sender_user_id` BIGINT UNSIGNED NULL,
                `seq` INT UNSIGNED NOT NULL,
                `body` TEXT NULL,
                `meta` JSON NULL,
                `created_at` TIMESTAMP NOT NULL,
                PRIMARY KEY (`id`, `created_at`),
                KEY `ix_interview_messages_session_seq` (`session_id`, `seq`),
                KEY `ix_interview_messages_interview` (`interview_id`),
                KEY `ix_interview_messages_workspace_created` (`workspace_id`, `created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    // ----------------------------------------------------------------- interview_media

    private function createInterviewMedia(Database $db): void
    {
        if ($this->hasTable($db, 'interview_media')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `interview_media` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `workspace_id` BIGINT UNSIGNED NOT NULL,
                `interview_id` BIGINT UNSIGNED NOT NULL,
                `session_id` BIGINT UNSIGNED NULL,
                `question_id` BIGINT UNSIGNED NULL,
                `type_id` BIGINT UNSIGNED NOT NULL,
                `file_id` BIGINT UNSIGNED NULL,
                `duration_seconds` INT UNSIGNED NULL,
                `meta` JSON NULL,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                `deleted_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_interview_media_uuid` (`uuid`),
                KEY `ix_interview_media_interview` (`interview_id`),
                KEY `ix_interview_media_session` (`session_id`),
                KEY `ix_interview_media_question` (`question_id`),
                KEY `ix_interview_media_workspace` (`workspace_id`),
                KEY `ix_interview_media_type` (`type_id`),
                KEY `ix_interview_media_file` (`file_id`),
                KEY `ix_interview_media_deleted_at` (`deleted_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    // ------------------------------------------------------------- interview_questions

    private function createInterviewQuestions(Database $db): void
    {
        if ($this->hasTable($db, 'interview_questions')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `interview_questions` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `workspace_id` BIGINT UNSIGNED NOT NULL,
                `interview_id` BIGINT UNSIGNED NULL,
                `job_id` BIGINT UNSIGNED NULL,
                `type_id` BIGINT UNSIGNED NOT NULL,
                `criterion_id` BIGINT UNSIGNED NULL,
                `text` TEXT NOT NULL,
                `options` JSON NULL,
                `expected` JSON NULL,
                `is_ai_generated` TINYINT(1) NOT NULL DEFAULT 0,
                `sort_order` INT NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                `deleted_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_interview_questions_uuid` (`uuid`),
                KEY `ix_interview_questions_interview_sort` (`interview_id`, `sort_order`),
                KEY `ix_interview_questions_workspace` (`workspace_id`),
                KEY `ix_interview_questions_job` (`job_id`),
                KEY `ix_interview_questions_type` (`type_id`),
                KEY `ix_interview_questions_criterion` (`criterion_id`),
                KEY `ix_interview_questions_deleted_at` (`deleted_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    // --------------------------------------------------------------- interview_answers

    private function createInterviewAnswers(Database $db): void
    {
        if ($this->hasTable($db, 'interview_answers')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `interview_answers` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `workspace_id` BIGINT UNSIGNED NOT NULL,
                `interview_id` BIGINT UNSIGNED NOT NULL,
                `session_id` BIGINT UNSIGNED NULL,
                `question_id` BIGINT UNSIGNED NOT NULL,
                `user_id` BIGINT UNSIGNED NOT NULL,
                `answer_text` TEXT NULL,
                `selected_options` JSON NULL,
                `media_id` BIGINT UNSIGNED NULL,
                `answer_file_id` BIGINT UNSIGNED NULL,
                `ai_score` DECIMAL(5,2) NULL,
                `ai_feedback` JSON NULL,
                `answered_at` TIMESTAMP NULL DEFAULT NULL,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                `deleted_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_interview_answers_uuid` (`uuid`),
                UNIQUE KEY `uq_interview_answers_session_question` (`session_id`, `question_id`),
                KEY `ix_interview_answers_interview` (`interview_id`),
                KEY `ix_interview_answers_question` (`question_id`),
                KEY `ix_interview_answers_user` (`user_id`),
                KEY `ix_interview_answers_workspace` (`workspace_id`),
                KEY `ix_interview_answers_media` (`media_id`),
                KEY `ix_interview_answers_answer_file` (`answer_file_id`),
                KEY `ix_interview_answers_deleted_at` (`deleted_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    // ---------------------------------------------------------------- interview_scores

    private function createInterviewScores(Database $db): void
    {
        if ($this->hasTable($db, 'interview_scores')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `interview_scores` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `workspace_id` BIGINT UNSIGNED NOT NULL,
                `interview_id` BIGINT UNSIGNED NOT NULL,
                `session_id` BIGINT UNSIGNED NULL,
                `criterion_id` BIGINT UNSIGNED NOT NULL,
                `scored_by` BIGINT UNSIGNED NULL,
                `is_ai` TINYINT(1) NOT NULL DEFAULT 0,
                `score` DECIMAL(5,2) NOT NULL,
                `max_score` DECIMAL(5,2) NULL,
                `weight` DECIMAL(5,2) NULL,
                `comment` TEXT NULL,
                `scored_at` TIMESTAMP NULL DEFAULT NULL,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_interview_scores_uuid` (`uuid`),
                UNIQUE KEY `uq_interview_scores_unique_line` (`interview_id`, `criterion_id`, `scored_by`, `session_id`),
                KEY `ix_interview_scores_interview` (`interview_id`),
                KEY `ix_interview_scores_session` (`session_id`),
                KEY `ix_interview_scores_criterion` (`criterion_id`),
                KEY `ix_interview_scores_scored_by` (`scored_by`),
                KEY `ix_interview_scores_workspace` (`workspace_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    // ----------------------------------------------------------- interview_ai_analyses

    private function createInterviewAiAnalyses(Database $db): void
    {
        if ($this->hasTable($db, 'interview_ai_analyses')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `interview_ai_analyses` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `workspace_id` BIGINT UNSIGNED NOT NULL,
                `interview_id` BIGINT UNSIGNED NOT NULL,
                `session_id` BIGINT UNSIGNED NULL,
                `provider_id` BIGINT UNSIGNED NULL,
                `model_id` BIGINT UNSIGNED NULL,
                `overall_score` DECIMAL(5,2) NULL,
                `analysis` JSON NULL,
                `summary` TEXT NULL,
                `tokens_used` INT UNSIGNED NULL,
                `error` TEXT NULL,
                `generated_at` TIMESTAMP NULL DEFAULT NULL,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_interview_ai_analyses_uuid` (`uuid`),
                KEY `ix_interview_ai_analyses_interview` (`interview_id`),
                KEY `ix_interview_ai_analyses_session` (`session_id`),
                KEY `ix_interview_ai_analyses_provider` (`provider_id`),
                KEY `ix_interview_ai_analyses_model` (`model_id`),
                KEY `ix_interview_ai_analyses_workspace` (`workspace_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    // ---------------------------------------------------------------- interview_tokens

    private function createInterviewTokens(Database $db): void
    {
        if ($this->hasTable($db, 'interview_tokens')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `interview_tokens` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `workspace_id` BIGINT UNSIGNED NOT NULL,
                `interview_id` BIGINT UNSIGNED NOT NULL,
                `user_id` BIGINT UNSIGNED NOT NULL,
                `token_hash` CHAR(64) NOT NULL,
                `expires_at` TIMESTAMP NOT NULL,
                `used_at` TIMESTAMP NULL DEFAULT NULL,
                `revoked_at` TIMESTAMP NULL DEFAULT NULL,
                `last_used_ip` VARBINARY(16) NULL,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_interview_tokens_uuid` (`uuid`),
                UNIQUE KEY `uq_interview_tokens_token_hash` (`token_hash`),
                KEY `ix_interview_tokens_interview` (`interview_id`),
                KEY `ix_interview_tokens_user` (`user_id`),
                KEY `ix_interview_tokens_workspace` (`workspace_id`),
                KEY `ix_interview_tokens_expires_at` (`expires_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    // ------------------------------------------------------------------ interview_logs
    // scale: partition candidate by RANGE(created_at) — billions-row append-only,
    // FK-light, uuid omitted, composite PK (id, created_at). No physical PARTITION here.

    private function createInterviewLogs(Database $db): void
    {
        if ($this->hasTable($db, 'interview_logs')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `interview_logs` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `workspace_id` BIGINT UNSIGNED NOT NULL,
                `interview_id` BIGINT UNSIGNED NOT NULL,
                `session_id` BIGINT UNSIGNED NULL,
                `event_code` SMALLINT UNSIGNED NOT NULL,
                `actor_user_id` BIGINT UNSIGNED NULL,
                `level` TINYINT UNSIGNED NOT NULL DEFAULT 0,
                `message` VARCHAR(512) NULL,
                `context` JSON NULL,
                `ip` VARBINARY(16) NULL,
                `created_at` TIMESTAMP NOT NULL,
                PRIMARY KEY (`id`, `created_at`),
                KEY `ix_interview_logs_interview_created` (`interview_id`, `created_at`),
                KEY `ix_interview_logs_workspace_created` (`workspace_id`, `created_at`),
                KEY `ix_interview_logs_event` (`event_code`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    // --------------------------------------------------------- interview_participants

    private function createInterviewParticipants(Database $db): void
    {
        if ($this->hasTable($db, 'interview_participants')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `interview_participants` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `workspace_id` BIGINT UNSIGNED NOT NULL,
                `interview_id` BIGINT UNSIGNED NOT NULL,
                `user_id` BIGINT UNSIGNED NOT NULL,
                `role_id` BIGINT UNSIGNED NOT NULL,
                `response_id` BIGINT UNSIGNED NULL,
                `is_organizer` TINYINT(1) NOT NULL DEFAULT 0,
                `responded_at` TIMESTAMP NULL DEFAULT NULL,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_interview_participants_uuid` (`uuid`),
                UNIQUE KEY `uq_interview_participants_interview_user` (`interview_id`, `user_id`),
                KEY `ix_interview_participants_user` (`user_id`),
                KEY `ix_interview_participants_workspace` (`workspace_id`),
                KEY `ix_interview_participants_role` (`role_id`),
                KEY `ix_interview_participants_response` (`response_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    // ------------------------------------------------------------------------ seeding

    private function seedApplicationStatuses(Database $db): void
    {
        if (! $this->hasTable($db, 'application_statuses')) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        foreach ($this->applicationStatuses as [$key, $label, $color, $sort, $isDefault, $isInitial, $isTerminal]) {
            $exists = (int) $db->scalar(
                'SELECT COUNT(*) FROM `application_statuses` WHERE `workspace_id` IS NULL AND `key` = ?',
                [$key]
            ) > 0;
            if ($exists) {
                continue;
            }
            $db->table('application_statuses')->insert([
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

    private function seedInterviewStatuses(Database $db): void
    {
        if (! $this->hasTable($db, 'interview_statuses')) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        foreach ($this->interviewStatuses as [$key, $label, $color, $sort, $isDefault, $isInitial, $isTerminal]) {
            $exists = (int) $db->scalar(
                'SELECT COUNT(*) FROM `interview_statuses` WHERE `workspace_id` IS NULL AND `key` = ?',
                [$key]
            ) > 0;
            if ($exists) {
                continue;
            }
            $db->table('interview_statuses')->insert([
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
