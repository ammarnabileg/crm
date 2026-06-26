<?php

declare(strict_types=1);

use App\Core\Database;
use Database\Migration;

/**
 * Tracks each user's progress through their role-appropriate onboarding flow.
 * The flow key (e.g. owner, hr, candidate, super-admin) is chosen at runtime
 * from the user's effective roles, so the same user gets a different onboarding
 * experience per company context.
 */
return new class extends Migration {
    public function up(Database $db): void
    {
        $db->unprepared(
            "CREATE TABLE `onboarding_progress` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `user_id` BIGINT UNSIGNED NOT NULL,
                `company_id` BIGINT UNSIGNED NULL,
                `flow` VARCHAR(60) NOT NULL,
                `current_step` INT NOT NULL DEFAULT 0,
                `completed_steps` JSON NULL,
                `is_completed` TINYINT(1) NOT NULL DEFAULT 0,
                `completed_at` TIMESTAMP NULL DEFAULT NULL,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `onboarding_user_company_flow_unique` (`user_id`, `company_id`, `flow`),
                CONSTRAINT `onboarding_user_id_foreign` FOREIGN KEY (`user_id`)
                    REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `onboarding_company_id_foreign` FOREIGN KEY (`company_id`)
                    REFERENCES `companies` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    public function down(Database $db): void
    {
        $db->unprepared('DROP TABLE IF EXISTS `onboarding_progress`');
    }
};
