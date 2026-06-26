<?php

declare(strict_types=1);

use App\Core\Database;
use Database\Migration;

/**
 * Subscription plans are fully data-driven. The business currently sells one
 * plan (50 SAR / month) but the schema supports an unlimited number of plans —
 * adding a plan is an INSERT, never a code change. Feature flags and numeric
 * limits live in JSON so plans can gate functionality without migrations.
 */
return new class extends Migration {
    public function up(Database $db): void
    {
        $db->unprepared(
            "CREATE TABLE `plans` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(120) NOT NULL,
                `slug` VARCHAR(120) NOT NULL,
                `description` VARCHAR(500) NULL,
                `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                `currency` VARCHAR(3) NOT NULL DEFAULT 'SAR',
                `interval` ENUM('monthly','yearly') NOT NULL DEFAULT 'monthly',
                `trial_days` INT NOT NULL DEFAULT 0,
                `features` JSON NULL,
                `limits` JSON NULL,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `is_public` TINYINT(1) NOT NULL DEFAULT 1,
                `sort_order` INT NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `plans_slug_unique` (`slug`),
                KEY `plans_is_active_index` (`is_active`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    public function down(Database $db): void
    {
        $db->unprepared('DROP TABLE IF EXISTS `plans`');
    }
};
