<?php

declare(strict_types=1);

use App\Core\Database;
use Database\Migration;

/**
 * The single source of identity for the whole platform.
 *
 * There is exactly ONE users table. A person is never a "candidate row" or an
 * "HR row" — they are a user whose capabilities come entirely from the roles
 * and permissions attached to their company memberships (and, for platform
 * staff, to global roles). The same user id can simultaneously be a candidate
 * in one company, an owner of another, and a super admin of the platform.
 */
return new class extends Migration {
    public function up(Database $db): void
    {
        $db->unprepared(
            "CREATE TABLE `users` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(150) NOT NULL,
                `email` VARCHAR(190) NOT NULL,
                `password` VARCHAR(255) NOT NULL,
                `phone` VARCHAR(40) NULL,
                `avatar` VARCHAR(255) NULL,
                `locale` VARCHAR(5) NOT NULL DEFAULT 'en',
                `timezone` VARCHAR(64) NOT NULL DEFAULT 'Asia/Riyadh',
                `status` ENUM('active','suspended','pending') NOT NULL DEFAULT 'active',
                `email_verified_at` TIMESTAMP NULL DEFAULT NULL,
                `last_login_at` TIMESTAMP NULL DEFAULT NULL,
                `last_login_ip` VARCHAR(45) NULL DEFAULT NULL,
                `remember_token` VARCHAR(100) NULL DEFAULT NULL,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `users_email_unique` (`email`),
                KEY `users_status_index` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    public function down(Database $db): void
    {
        $db->unprepared('DROP TABLE IF EXISTS `users`');
    }
};
