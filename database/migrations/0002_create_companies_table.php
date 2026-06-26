<?php

declare(strict_types=1);

use App\Core\Database;
use Database\Migration;

/**
 * Companies ARE the tenants. Every tenant-scoped row in the system carries a
 * company_id and is isolated from every other company. A company is created by
 * a user (who becomes its owner) or provisioned by a super admin.
 */
return new class extends Migration {
    public function up(Database $db): void
    {
        $db->unprepared(
            "CREATE TABLE `companies` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(150) NOT NULL,
                `slug` VARCHAR(160) NOT NULL,
                `owner_id` BIGINT UNSIGNED NOT NULL,
                `logo` VARCHAR(255) NULL,
                `locale` VARCHAR(5) NOT NULL DEFAULT 'en',
                `timezone` VARCHAR(64) NOT NULL DEFAULT 'Asia/Riyadh',
                `status` ENUM('trial','active','suspended','canceled') NOT NULL DEFAULT 'trial',
                `settings` JSON NULL,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `companies_slug_unique` (`slug`),
                KEY `companies_owner_id_index` (`owner_id`),
                KEY `companies_status_index` (`status`),
                CONSTRAINT `companies_owner_id_foreign` FOREIGN KEY (`owner_id`)
                    REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    public function down(Database $db): void
    {
        $db->unprepared('DROP TABLE IF EXISTS `companies`');
    }
};
