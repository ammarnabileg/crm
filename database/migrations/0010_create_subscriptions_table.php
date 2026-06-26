<?php

declare(strict_types=1);

use App\Core\Database;
use Database\Migration;

/**
 * A company's subscription to a plan. Tracks lifecycle (trial -> active ->
 * past_due/canceled/expired) and the billing window. Amount/currency are
 * snapshotted from the plan at subscribe time so later plan price changes do
 * not retroactively alter existing subscriptions.
 */
return new class extends Migration {
    public function up(Database $db): void
    {
        $db->unprepared(
            "CREATE TABLE `subscriptions` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `company_id` BIGINT UNSIGNED NOT NULL,
                `plan_id` BIGINT UNSIGNED NOT NULL,
                `status` ENUM('trialing','active','past_due','canceled','expired') NOT NULL DEFAULT 'trialing',
                `amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                `currency` VARCHAR(3) NOT NULL DEFAULT 'SAR',
                `trial_ends_at` TIMESTAMP NULL DEFAULT NULL,
                `starts_at` TIMESTAMP NULL DEFAULT NULL,
                `ends_at` TIMESTAMP NULL DEFAULT NULL,
                `canceled_at` TIMESTAMP NULL DEFAULT NULL,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `subscriptions_company_id_index` (`company_id`),
                KEY `subscriptions_plan_id_index` (`plan_id`),
                KEY `subscriptions_status_index` (`status`),
                CONSTRAINT `subscriptions_company_id_foreign` FOREIGN KEY (`company_id`)
                    REFERENCES `companies` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `subscriptions_plan_id_foreign` FOREIGN KEY (`plan_id`)
                    REFERENCES `plans` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    public function down(Database $db): void
    {
        $db->unprepared('DROP TABLE IF EXISTS `subscriptions`');
    }
};
