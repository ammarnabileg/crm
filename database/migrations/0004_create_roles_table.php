<?php

declare(strict_types=1);

use App\Core\Database;
use Database\Migration;

/**
 * Roles power the RBAC engine. A role with company_id = NULL is a GLOBAL role
 * (e.g. super-admin) assigned directly to users; a role with a company_id is a
 * tenant role assigned through a membership. Roles support single-parent
 * inheritance via parent_id, so a child role inherits all of its parent's
 * permissions. System roles (is_system = 1) are protected from deletion.
 */
return new class extends Migration {
    public function up(Database $db): void
    {
        $db->unprepared(
            "CREATE TABLE `roles` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `company_id` BIGINT UNSIGNED NULL,
                `parent_id` BIGINT UNSIGNED NULL,
                `name` VARCHAR(120) NOT NULL,
                `slug` VARCHAR(120) NOT NULL,
                `description` VARCHAR(255) NULL,
                `is_system` TINYINT(1) NOT NULL DEFAULT 0,
                `priority` INT NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `roles_company_slug_unique` (`company_id`, `slug`),
                KEY `roles_parent_id_index` (`parent_id`),
                CONSTRAINT `roles_company_id_foreign` FOREIGN KEY (`company_id`)
                    REFERENCES `companies` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `roles_parent_id_foreign` FOREIGN KEY (`parent_id`)
                    REFERENCES `roles` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    public function down(Database $db): void
    {
        $db->unprepared('DROP TABLE IF EXISTS `roles`');
    }
};
