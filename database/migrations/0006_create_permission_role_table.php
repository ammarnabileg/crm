<?php

declare(strict_types=1);

use App\Core\Database;
use Database\Migration;

/**
 * Which permissions a role grants. Combined with role inheritance, a role's
 * effective permission set is its own rows here plus those of its ancestors.
 */
return new class extends Migration {
    public function up(Database $db): void
    {
        $db->unprepared(
            "CREATE TABLE `permission_role` (
                `role_id` BIGINT UNSIGNED NOT NULL,
                `permission_id` BIGINT UNSIGNED NOT NULL,
                PRIMARY KEY (`role_id`, `permission_id`),
                KEY `permission_role_permission_id_index` (`permission_id`),
                CONSTRAINT `permission_role_role_id_foreign` FOREIGN KEY (`role_id`)
                    REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `permission_role_permission_id_foreign` FOREIGN KEY (`permission_id`)
                    REFERENCES `permissions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    public function down(Database $db): void
    {
        $db->unprepared('DROP TABLE IF EXISTS `permission_role`');
    }
};
