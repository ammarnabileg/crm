<?php

declare(strict_types=1);

use App\Core\Database;
use Database\Migration;

/**
 * Audit trail. Records meaningful actions (logins, role changes, company
 * creation, AI key updates, ...) with the actor, the affected subject and a
 * JSON property bag. company_id is nullable for platform-level events.
 */
return new class extends Migration {
    public function up(Database $db): void
    {
        $db->unprepared(
            "CREATE TABLE `activity_log` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `company_id` BIGINT UNSIGNED NULL,
                `user_id` BIGINT UNSIGNED NULL,
                `action` VARCHAR(120) NOT NULL,
                `subject_type` VARCHAR(120) NULL,
                `subject_id` BIGINT UNSIGNED NULL,
                `description` VARCHAR(255) NULL,
                `properties` JSON NULL,
                `ip` VARCHAR(45) NULL,
                `user_agent` VARCHAR(255) NULL,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `activity_log_company_id_index` (`company_id`),
                KEY `activity_log_user_id_index` (`user_id`),
                KEY `activity_log_action_index` (`action`),
                CONSTRAINT `activity_log_company_id_foreign` FOREIGN KEY (`company_id`)
                    REFERENCES `companies` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `activity_log_user_id_foreign` FOREIGN KEY (`user_id`)
                    REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    public function down(Database $db): void
    {
        $db->unprepared('DROP TABLE IF EXISTS `activity_log`');
    }
};
