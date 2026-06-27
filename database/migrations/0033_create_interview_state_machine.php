<?php

declare(strict_types=1);

use App\Core\Database;
use Database\Migration;

/**
 * AI Interview Engine — P1: Interview State Machine (docs/51 §5, D11 extension).
 *
 * The interview is driven by a configuration-driven STATE MACHINE, not a message
 * chain. `interview_states` is the catalog of workflow stages (system defaults +
 * per-tenant overrides), each with ordering, initial/terminal flags and an optional
 * timeout; `interview_state_transitions` is the append-only per-interview audit of
 * stage changes; `interviews.state_id` points at the current stage. Legal
 * transitions are enforced by App\Services\Interview\StateMachine.
 *
 * Config-driven (no ENUMs), workspace-scoped, idempotent via information_schema
 * guards. Self-contained: all FK targets (workspaces, users, interviews) exist.
 */
return new class extends Migration {
    /** Seeded system stages: [key, label, sort, is_initial, is_terminal, timeout_minutes, color]. */
    private array $states = [
        ['draft', 'Draft', 1, 1, 0, null, '#94a3b8'],
        ['scheduled', 'Scheduled', 2, 0, 0, null, '#0ea5e9'],
        ['waiting', 'Waiting', 3, 0, 0, null, '#f59e0b'],
        ['identity_verification', 'Identity Verification', 4, 0, 0, 10, '#6366f1'],
        ['device_check', 'Camera & Microphone Check', 5, 0, 0, 10, '#6366f1'],
        ['environment_check', 'Environment Check', 6, 0, 0, 10, '#6366f1'],
        ['introduction', 'Introduction', 7, 0, 0, 15, '#8b5cf6'],
        ['ice_breaking', 'Ice Breaking', 8, 0, 0, 15, '#8b5cf6'],
        ['cv_review', 'CV Review', 9, 0, 0, 20, '#8b5cf6'],
        ['experience_discussion', 'Experience Discussion', 10, 0, 0, 30, '#8b5cf6'],
        ['technical_assessment', 'Technical Assessment', 11, 0, 0, 45, '#0d9488'],
        ['behavioral_assessment', 'Behavioral Assessment', 12, 0, 0, 30, '#0d9488'],
        ['scenario_questions', 'Scenario Questions', 13, 0, 0, 30, '#0d9488'],
        ['problem_solving', 'Problem Solving', 14, 0, 0, 45, '#0d9488'],
        ['culture_fit', 'Culture Fit', 15, 0, 0, 20, '#0d9488'],
        ['candidate_questions', 'Candidate Questions', 16, 0, 0, 15, '#8b5cf6'],
        ['final_evaluation', 'Final Evaluation', 17, 0, 0, null, '#d97706'],
        ['ai_review', 'AI Review', 18, 0, 0, null, '#d97706'],
        ['human_review', 'Human Review', 19, 0, 0, null, '#d97706'],
        ['completed', 'Completed', 20, 0, 0, null, '#16a34a'],
        ['archived', 'Archived', 21, 0, 1, null, '#6b7280'],
    ];

    public function up(Database $db): void
    {
        // 1) interview_states — config-driven stage catalog.
        if (! $this->hasTable($db, 'interview_states')) {
            $db->unprepared(
                "CREATE TABLE `interview_states` (
                    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `uuid` CHAR(36) NOT NULL,
                    `workspace_id` BIGINT UNSIGNED NULL,
                    `key` VARCHAR(60) NOT NULL,
                    `label` VARCHAR(120) NOT NULL,
                    `description` VARCHAR(255) NULL,
                    `color` VARCHAR(20) NULL,
                    `sort_order` INT NOT NULL DEFAULT 0,
                    `is_initial` TINYINT(1) NOT NULL DEFAULT 0,
                    `is_terminal` TINYINT(1) NOT NULL DEFAULT 0,
                    `is_system` TINYINT(1) NOT NULL DEFAULT 0,
                    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                    `timeout_minutes` INT UNSIGNED NULL,
                    `meta` JSON NULL,
                    `created_at` TIMESTAMP NULL DEFAULT NULL,
                    `updated_at` TIMESTAMP NULL DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `interview_states_uuid_unique` (`uuid`),
                    UNIQUE KEY `interview_states_workspace_key_unique` (`workspace_id`, `key`),
                    KEY `interview_states_workspace_id_index` (`workspace_id`),
                    KEY `interview_states_active_sort_index` (`is_active`, `sort_order`),
                    CONSTRAINT `interview_states_workspace_id_foreign` FOREIGN KEY (`workspace_id`)
                        REFERENCES `workspaces` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );

            $now = date('Y-m-d H:i:s');
            foreach ($this->states as [$key, $label, $sort, $initial, $terminal, $timeout, $color]) {
                $db->table('interview_states')->insert([
                    'uuid'            => $this->uuid($db),
                    'workspace_id'    => null,
                    'key'             => $key,
                    'label'           => $label,
                    'color'           => $color,
                    'sort_order'      => $sort,
                    'is_initial'      => $initial,
                    'is_terminal'     => $terminal,
                    'is_system'       => 1,
                    'is_active'       => 1,
                    'timeout_minutes' => $timeout,
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ]);
            }
        }

        // 2) interview_state_transitions — append-only per-interview stage audit.
        if (! $this->hasTable($db, 'interview_state_transitions')) {
            $db->unprepared(
                "CREATE TABLE `interview_state_transitions` (
                    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `uuid` CHAR(36) NOT NULL,
                    `workspace_id` BIGINT UNSIGNED NOT NULL,
                    `interview_id` BIGINT UNSIGNED NOT NULL,
                    `from_state_id` BIGINT UNSIGNED NULL,
                    `to_state_id` BIGINT UNSIGNED NOT NULL,
                    `changed_by` BIGINT UNSIGNED NULL,
                    `reason` VARCHAR(255) NULL,
                    `meta` JSON NULL,
                    `created_at` TIMESTAMP NULL DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `interview_state_transitions_uuid_unique` (`uuid`),
                    KEY `interview_state_transitions_workspace_id_index` (`workspace_id`),
                    KEY `interview_state_transitions_interview_id_index` (`interview_id`),
                    KEY `interview_state_transitions_to_state_id_index` (`to_state_id`),
                    KEY `interview_state_transitions_from_state_id_index` (`from_state_id`),
                    KEY `interview_state_transitions_changed_by_index` (`changed_by`),
                    KEY `interview_state_transitions_created_at_index` (`created_at`),
                    CONSTRAINT `interview_state_transitions_workspace_id_foreign` FOREIGN KEY (`workspace_id`)
                        REFERENCES `workspaces` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                    CONSTRAINT `interview_state_transitions_interview_id_foreign` FOREIGN KEY (`interview_id`)
                        REFERENCES `interviews` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                    CONSTRAINT `interview_state_transitions_from_state_id_foreign` FOREIGN KEY (`from_state_id`)
                        REFERENCES `interview_states` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
                    CONSTRAINT `interview_state_transitions_to_state_id_foreign` FOREIGN KEY (`to_state_id`)
                        REFERENCES `interview_states` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
                    CONSTRAINT `interview_state_transitions_changed_by_foreign` FOREIGN KEY (`changed_by`)
                        REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }

        // 3) interviews.state_id — the current workflow stage.
        if ($this->hasTable($db, 'interviews') && ! $this->hasColumn($db, 'interviews', 'state_id')) {
            $db->unprepared('ALTER TABLE `interviews` ADD COLUMN `state_id` BIGINT UNSIGNED NULL AFTER `interview_status_id`');
            $db->unprepared('ALTER TABLE `interviews` ADD KEY `interviews_state_id_index` (`state_id`)');
            $db->unprepared(
                'ALTER TABLE `interviews` ADD CONSTRAINT `interviews_state_id_foreign`
                 FOREIGN KEY (`state_id`) REFERENCES `interview_states` (`id`)
                 ON DELETE RESTRICT ON UPDATE CASCADE'
            );
        }
    }

    public function down(Database $db): void
    {
        if ($this->hasColumn($db, 'interviews', 'state_id')) {
            if ($this->hasConstraint($db, 'interviews', 'interviews_state_id_foreign')) {
                $db->unprepared('ALTER TABLE `interviews` DROP FOREIGN KEY `interviews_state_id_foreign`');
            }
            $db->unprepared('ALTER TABLE `interviews` DROP COLUMN `state_id`');
        }
        $db->unprepared('DROP TABLE IF EXISTS `interview_state_transitions`');
        $db->unprepared('DROP TABLE IF EXISTS `interview_states`');
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
};
