<?php

declare(strict_types=1);

use App\Core\Database;
use Database\Migration;

/**
 * Per-tenant AI provider credentials. The platform itself ships with NO AI
 * keys — every company supplies its own (OpenAI, Anthropic, Gemini, DeepSeek,
 * Azure OpenAI, HeyGen, ...). The `credentials` column stores an encrypted
 * JSON blob (AES-256-GCM) so secrets are never persisted in plaintext.
 */
return new class extends Migration {
    public function up(Database $db): void
    {
        $db->unprepared(
            "CREATE TABLE `ai_credentials` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `company_id` BIGINT UNSIGNED NOT NULL,
                `provider` VARCHAR(40) NOT NULL,
                `label` VARCHAR(120) NULL,
                `credentials` TEXT NOT NULL,
                `meta` JSON NULL,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `is_default` TINYINT(1) NOT NULL DEFAULT 0,
                `last_used_at` TIMESTAMP NULL DEFAULT NULL,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `ai_credentials_company_provider_unique` (`company_id`, `provider`),
                CONSTRAINT `ai_credentials_company_id_foreign` FOREIGN KEY (`company_id`)
                    REFERENCES `companies` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    public function down(Database $db): void
    {
        $db->unprepared('DROP TABLE IF EXISTS `ai_credentials`');
    }
};
