<?php

declare(strict_types=1);

use App\Core\Database;
use Database\Migration;

/**
 * ATS & Recruitment Workflow (docs/53) — additive support for assignment + tasks.
 *
 * The recruitment schema (jobs, applications, pipelines, pipeline_stages, offers,
 * schedules, meetings, pools, notes, status_histories, …) already exists from the
 * Database Bible build; this migration ONLY adds, backward-compatibly:
 *  - `applications.assigned_recruiter_id` / `assigned_interviewer_id` (nullable FKs)
 *    so an application can be assigned to a recruiter/interviewer;
 *  - a generic `tasks` table (the existing `scheduled_tasks` is the CRON scheduler,
 *    not recruiter to-dos) linked polymorphically to a job/candidate/interview/etc.
 *
 * Guarded with information_schema so it is idempotent and never disturbs existing
 * columns/tables. No renames, no drops of existing structures.
 */
return new class extends Migration {
    public function up(Database $db): void
    {
        if ($this->hasTable($db, 'applications')) {
            if (! $this->hasColumn($db, 'applications', 'assigned_recruiter_id')) {
                $db->unprepared('ALTER TABLE `applications` ADD COLUMN `assigned_recruiter_id` BIGINT UNSIGNED NULL AFTER `user_id`');
                $db->unprepared('ALTER TABLE `applications` ADD KEY `applications_assigned_recruiter_id_index` (`assigned_recruiter_id`)');
                if ($this->hasTable($db, 'users')) {
                    $db->unprepared('ALTER TABLE `applications` ADD CONSTRAINT `applications_assigned_recruiter_id_foreign` FOREIGN KEY (`assigned_recruiter_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE');
                }
            }
            if (! $this->hasColumn($db, 'applications', 'assigned_interviewer_id')) {
                $db->unprepared('ALTER TABLE `applications` ADD COLUMN `assigned_interviewer_id` BIGINT UNSIGNED NULL AFTER `assigned_recruiter_id`');
                $db->unprepared('ALTER TABLE `applications` ADD KEY `applications_assigned_interviewer_id_index` (`assigned_interviewer_id`)');
                if ($this->hasTable($db, 'users')) {
                    $db->unprepared('ALTER TABLE `applications` ADD CONSTRAINT `applications_assigned_interviewer_id_foreign` FOREIGN KEY (`assigned_interviewer_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE');
                }
            }
        }

        if (! $this->hasTable($db, 'tasks')) {
            $db->unprepared(
                "CREATE TABLE `tasks` (
                    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `uuid` CHAR(36) NOT NULL,
                    `workspace_id` BIGINT UNSIGNED NOT NULL,
                    `title` VARCHAR(200) NOT NULL,
                    `description` TEXT NULL,
                    `status` VARCHAR(20) NOT NULL DEFAULT 'open',
                    `priority` VARCHAR(20) NOT NULL DEFAULT 'normal',
                    `due_at` DATETIME NULL,
                    `assignee_id` BIGINT UNSIGNED NULL,
                    `related_type` VARCHAR(120) NULL,
                    `related_id` BIGINT UNSIGNED NULL,
                    `created_by` BIGINT UNSIGNED NULL,
                    `completed_at` TIMESTAMP NULL DEFAULT NULL,
                    `created_at` TIMESTAMP NULL DEFAULT NULL,
                    `updated_at` TIMESTAMP NULL DEFAULT NULL,
                    `deleted_at` TIMESTAMP NULL DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `tasks_uuid_unique` (`uuid`),
                    KEY `tasks_workspace_id_index` (`workspace_id`),
                    KEY `tasks_assignee_id_index` (`assignee_id`),
                    KEY `tasks_status_index` (`workspace_id`, `status`),
                    KEY `tasks_related_index` (`related_type`, `related_id`),
                    KEY `tasks_deleted_at_index` (`deleted_at`),
                    CONSTRAINT `tasks_workspace_id_foreign` FOREIGN KEY (`workspace_id`)
                        REFERENCES `workspaces` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                    CONSTRAINT `tasks_assignee_id_foreign` FOREIGN KEY (`assignee_id`)
                        REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                    CONSTRAINT `tasks_created_by_foreign` FOREIGN KEY (`created_by`)
                        REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }
    }

    public function down(Database $db): void
    {
        $db->unprepared('DROP TABLE IF EXISTS `tasks`');
        foreach (['assigned_interviewer_id' => 'applications_assigned_interviewer_id_foreign',
                  'assigned_recruiter_id' => 'applications_assigned_recruiter_id_foreign'] as $col => $fk) {
            if ($this->hasColumn($db, 'applications', $col)) {
                if ($this->hasConstraint($db, 'applications', $fk)) {
                    $db->unprepared("ALTER TABLE `applications` DROP FOREIGN KEY `{$fk}`");
                }
                $db->unprepared("ALTER TABLE `applications` DROP COLUMN `{$col}`");
            }
        }
    }

    private function hasTable(Database $db, string $table): bool
    {
        return (int) $db->scalar('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [$table]) > 0;
    }

    private function hasColumn(Database $db, string $table, string $column): bool
    {
        return (int) $db->scalar('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?', [$table, $column]) > 0;
    }

    private function hasConstraint(Database $db, string $table, string $constraint): bool
    {
        return (int) $db->scalar('SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?', [$table, $constraint]) > 0;
    }
};
