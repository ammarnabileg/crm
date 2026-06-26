<?php

declare(strict_types=1);

use App\Core\Database;
use Database\Migration;

/**
 * Assigns tenant roles to a membership — i.e. the roles a specific user holds
 * inside a specific company. This is how the same user can be an "Owner" in
 * company A and a "Candidate" in company B with no row duplication.
 */
return new class extends Migration {
    public function up(Database $db): void
    {
        $db->unprepared(
            "CREATE TABLE `membership_role` (
                `membership_id` BIGINT UNSIGNED NOT NULL,
                `role_id` BIGINT UNSIGNED NOT NULL,
                PRIMARY KEY (`membership_id`, `role_id`),
                KEY `membership_role_role_id_index` (`role_id`),
                CONSTRAINT `membership_role_membership_id_foreign` FOREIGN KEY (`membership_id`)
                    REFERENCES `memberships` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `membership_role_role_id_foreign` FOREIGN KEY (`role_id`)
                    REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    public function down(Database $db): void
    {
        $db->unprepared('DROP TABLE IF EXISTS `membership_role`');
    }
};
