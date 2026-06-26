<?php

declare(strict_types=1);

use App\Core\Database;
use Database\Migration;

/**
 * The user <-> company link. A user may belong to many companies; within each
 * one their capabilities are defined by the roles attached to THIS membership.
 */
return new class extends Migration {
    public function up(Database $db): void
    {
        $db->unprepared(
            "CREATE TABLE `memberships` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `company_id` BIGINT UNSIGNED NOT NULL,
                `user_id` BIGINT UNSIGNED NOT NULL,
                `status` ENUM('active','invited','suspended') NOT NULL DEFAULT 'active',
                `title` VARCHAR(120) NULL,
                `invited_by` BIGINT UNSIGNED NULL,
                `invited_at` TIMESTAMP NULL DEFAULT NULL,
                `joined_at` TIMESTAMP NULL DEFAULT NULL,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `memberships_company_user_unique` (`company_id`, `user_id`),
                KEY `memberships_user_id_index` (`user_id`),
                KEY `memberships_status_index` (`status`),
                CONSTRAINT `memberships_company_id_foreign` FOREIGN KEY (`company_id`)
                    REFERENCES `companies` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `memberships_user_id_foreign` FOREIGN KEY (`user_id`)
                    REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `memberships_invited_by_foreign` FOREIGN KEY (`invited_by`)
                    REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    public function down(Database $db): void
    {
        $db->unprepared('DROP TABLE IF EXISTS `memberships`');
    }
};
