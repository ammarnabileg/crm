<?php

declare(strict_types=1);

use App\Core\Database;
use Database\Migration;

/**
 * Assigns GLOBAL (platform-level) roles directly to users — most importantly
 * super-admin. These roles are not tied to any single company and grant
 * platform-wide capabilities that bypass tenant scoping.
 */
return new class extends Migration {
    public function up(Database $db): void
    {
        $db->unprepared(
            "CREATE TABLE `user_role` (
                `user_id` BIGINT UNSIGNED NOT NULL,
                `role_id` BIGINT UNSIGNED NOT NULL,
                PRIMARY KEY (`user_id`, `role_id`),
                KEY `user_role_role_id_index` (`role_id`),
                CONSTRAINT `user_role_user_id_foreign` FOREIGN KEY (`user_id`)
                    REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `user_role_role_id_foreign` FOREIGN KEY (`role_id`)
                    REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    public function down(Database $db): void
    {
        $db->unprepared('DROP TABLE IF EXISTS `user_role`');
    }
};
