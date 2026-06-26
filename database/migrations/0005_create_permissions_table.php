<?php

declare(strict_types=1);

use App\Core\Database;
use Database\Migration;

/**
 * The global catalogue of permissions. Permissions are defined once for the
 * whole platform (e.g. "users.view", "companies.manage") and grouped for the
 * role-editor UI. They are referenced by key throughout the codebase, never by
 * hard-coded user-type checks.
 */
return new class extends Migration {
    public function up(Database $db): void
    {
        $db->unprepared(
            "CREATE TABLE `permissions` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `key` VARCHAR(120) NOT NULL,
                `name` VARCHAR(150) NOT NULL,
                `group` VARCHAR(80) NOT NULL DEFAULT 'General',
                `description` VARCHAR(255) NULL,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `permissions_key_unique` (`key`),
                KEY `permissions_group_index` (`group`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    public function down(Database $db): void
    {
        $db->unprepared('DROP TABLE IF EXISTS `permissions`');
    }
};
