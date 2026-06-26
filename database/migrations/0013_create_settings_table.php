<?php

declare(strict_types=1);

use App\Core\Database;
use Database\Migration;

/**
 * Per-tenant key/value settings (company preferences, feature toggles, AI
 * defaults, etc.). Scoped by company_id like every other tenant table.
 */
return new class extends Migration {
    public function up(Database $db): void
    {
        $db->unprepared(
            "CREATE TABLE `settings` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `company_id` BIGINT UNSIGNED NOT NULL,
                `key` VARCHAR(120) NOT NULL,
                `value` TEXT NULL,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `settings_company_key_unique` (`company_id`, `key`),
                CONSTRAINT `settings_company_id_foreign` FOREIGN KEY (`company_id`)
                    REFERENCES `companies` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    public function down(Database $db): void
    {
        $db->unprepared('DROP TABLE IF EXISTS `settings`');
    }
};
