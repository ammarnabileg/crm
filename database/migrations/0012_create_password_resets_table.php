<?php

declare(strict_types=1);

use App\Core\Database;
use Database\Migration;

/**
 * Password reset tokens. Tokens are stored hashed (never in plaintext) and
 * expire after a configurable TTL. One active token per email.
 */
return new class extends Migration {
    public function up(Database $db): void
    {
        $db->unprepared(
            "CREATE TABLE `password_resets` (
                `email` VARCHAR(190) NOT NULL,
                `token` VARCHAR(255) NOT NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`email`),
                KEY `password_resets_token_index` (`token`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    public function down(Database $db): void
    {
        $db->unprepared('DROP TABLE IF EXISTS `password_resets`');
    }
};
