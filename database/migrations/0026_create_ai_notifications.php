<?php

declare(strict_types=1);

use App\Core\Database;
use Database\Migration;

/**
 * D8 — AI & Notifications (structure only; FKs live in 0126).
 *
 * Owns the multi-engine AI layer (global provider/model catalog, billions-scale
 * request/response/usage/cost/log/error/cache tables) and the notification
 * backbone (notifications, multi-language templates, channel catalog, per-user
 * preferences, send queue, delivery logs). See docs/database/09-AI-Notifications.
 *
 * NOTE: `tenant_ai_keys` is BUILT as `ai_credentials` (migration 0011) and is
 * NOT created here. The platform stores NO AI keys of its own.
 *
 * Scale (Bible §4/§7): `ai_requests`, `ai_responses`, `ai_logs`, `ai_errors`,
 * `notification_logs` (and `notifications`) are extreme/high-volume append
 * tables — FK-light (indexed workspace_id + created_at), narrow hot rows with
 * big payloads in LONGTEXT/JSON, and partition-READY by RANGE(created_at). No
 * physical PARTITION is applied here (InnoDB FKs/partitions are an ops step).
 * Per-doc, those append tables omit `uuid`; `ai_cache` also omits it (internal
 * cache, not a public resource).
 *
 * This file creates every table + ALL indexes but NO foreign keys; FKs are
 * added idempotently in 0126_fk_ai_notifications.php. Idempotent via
 * information_schema guards.
 */
return new class extends Migration {
    /** Global AI vendor catalog (Bible §8). [key, name, slug, auth_type, sort] */
    private array $providers = [
        // key, name, slug, capabilities, auth_type, required_fields, sort
        ['openai', 'OpenAI', 'openai', '{"chat":true,"completion":true,"embeddings":true,"transcription":true,"video":false}', 'api_key', '["api_key"]', 1],
        ['anthropic', 'Anthropic Claude', 'anthropic', '{"chat":true,"completion":true,"embeddings":false,"transcription":false,"video":false}', 'api_key', '["api_key"]', 2],
        ['gemini', 'Google Gemini', 'gemini', '{"chat":true,"completion":true,"embeddings":true,"transcription":false,"video":false}', 'api_key', '["api_key"]', 3],
        ['deepseek', 'DeepSeek', 'deepseek', '{"chat":true,"completion":true,"embeddings":false,"transcription":false,"video":false}', 'api_key', '["api_key"]', 4],
        ['azure_openai', 'Azure OpenAI', 'azure-openai', '{"chat":true,"completion":true,"embeddings":true,"transcription":true,"video":false}', 'api_key_endpoint', '["api_key","base_url","deployment","api_version"]', 5],
        ['heygen', 'HeyGen', 'heygen', '{"chat":false,"completion":false,"embeddings":false,"transcription":false,"video":true}', 'api_key', '["api_key"]', 6],
    ];

    /** Global delivery channel catalog (config-driven; [../26] §12). [key, name, driver, requires_address, is_default, sort] */
    private array $channels = [
        ['in_app', 'In-App', 'database', 0, 1, 1],
        ['email', 'Email', 'smtp', 1, 1, 2],
        ['sms', 'SMS', 'sms', 1, 0, 3],
        ['whatsapp', 'WhatsApp', 'whatsapp', 1, 0, 4],
        ['push', 'Push', 'push', 1, 0, 5],
        ['slack', 'Slack', 'slack', 1, 0, 6],
        ['teams', 'Microsoft Teams', 'teams', 1, 0, 7],
    ];

    public function up(Database $db): void
    {
        $this->createAiProviders($db);
        $this->createAiModels($db);
        $this->createAiRequests($db);
        $this->createAiResponses($db);
        $this->createAiUsage($db);
        $this->createAiCosts($db);
        $this->createAiLogs($db);
        $this->createAiErrors($db);
        $this->createAiCache($db);
        $this->createNotifications($db);
        $this->createNotificationTemplates($db);
        $this->createNotificationChannels($db);
        $this->createNotificationPreferences($db);
        $this->createNotificationQueue($db);
        $this->createNotificationLogs($db);

        // Seed small system catalogs (workspace_id NULL / is_system scope).
        $this->seedAiProviders($db);
        $this->seedAiModels($db);
        $this->seedNotificationChannels($db);
    }

    public function down(Database $db): void
    {
        foreach ([
            'notification_logs',
            'notification_queue',
            'notification_preferences',
            'notification_channels',
            'notification_templates',
            'notifications',
            'ai_cache',
            'ai_errors',
            'ai_logs',
            'ai_costs',
            'ai_usage',
            'ai_responses',
            'ai_requests',
            'ai_models',
            'ai_providers',
        ] as $table) {
            $db->unprepared("DROP TABLE IF EXISTS `{$table}`");
        }
    }

    // -----------------------------------------------------------------
    // AI layer
    // -----------------------------------------------------------------

    private function createAiProviders(Database $db): void
    {
        if ($this->hasTable($db, 'ai_providers')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `ai_providers` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `key` VARCHAR(40) NOT NULL,
                `name` VARCHAR(120) NOT NULL,
                `slug` VARCHAR(60) NOT NULL,
                `capabilities` JSON NOT NULL,
                `auth_type` VARCHAR(30) NOT NULL DEFAULT 'api_key',
                `required_fields` JSON NULL,
                `base_url` VARCHAR(255) NULL,
                `logo_url` VARCHAR(255) NULL,
                `website_url` VARCHAR(255) NULL,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `sort_order` INT NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                `deleted_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `ai_providers_uuid_unique` (`uuid`),
                UNIQUE KEY `ai_providers_key_unique` (`key`),
                UNIQUE KEY `ai_providers_slug_unique` (`slug`),
                KEY `ai_providers_active_sort_index` (`is_active`, `sort_order`),
                KEY `ai_providers_deleted_at_index` (`deleted_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    private function createAiModels(Database $db): void
    {
        if ($this->hasTable($db, 'ai_models')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `ai_models` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `provider_id` BIGINT UNSIGNED NOT NULL,
                `key` VARCHAR(80) NOT NULL,
                `name` VARCHAR(120) NOT NULL,
                `capability` VARCHAR(30) NOT NULL DEFAULT 'chat',
                `context_window` INT UNSIGNED NULL,
                `max_output_tokens` INT UNSIGNED NULL,
                `input_price_per_1k` DECIMAL(12,6) NULL,
                `output_price_per_1k` DECIMAL(12,6) NULL,
                `currency_id` BIGINT UNSIGNED NULL,
                `supports_streaming` TINYINT(1) NOT NULL DEFAULT 0,
                `supports_vision` TINYINT(1) NOT NULL DEFAULT 0,
                `meta` JSON NULL,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `is_default` TINYINT(1) NOT NULL DEFAULT 0,
                `sort_order` INT NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                `deleted_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `ai_models_uuid_unique` (`uuid`),
                UNIQUE KEY `ai_models_provider_key_unique` (`provider_id`, `key`),
                KEY `ai_models_provider_id_index` (`provider_id`),
                KEY `ai_models_currency_id_index` (`currency_id`),
                KEY `ai_models_provider_capability_index` (`provider_id`, `capability`, `is_active`),
                KEY `ai_models_deleted_at_index` (`deleted_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    // scale: partition candidate by RANGE(created_at) — billions of rows, FK-light, no uuid.
    private function createAiRequests(Database $db): void
    {
        if ($this->hasTable($db, 'ai_requests')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `ai_requests` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `workspace_id` BIGINT UNSIGNED NOT NULL,
                `tenant_ai_key_id` BIGINT UNSIGNED NULL,
                `provider_id` BIGINT UNSIGNED NULL,
                `model_id` BIGINT UNSIGNED NULL,
                `model_key` VARCHAR(80) NULL,
                `request_type_id` BIGINT UNSIGNED NULL,
                `capability` VARCHAR(30) NOT NULL DEFAULT 'chat',
                `subject_type` VARCHAR(60) NULL,
                `subject_id` BIGINT UNSIGNED NULL,
                `user_id` BIGINT UNSIGNED NULL,
                `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
                `prompt_tokens` INT UNSIGNED NULL,
                `max_tokens` INT UNSIGNED NULL,
                `request_hash` CHAR(64) NULL,
                `request_payload` LONGTEXT NULL,
                `idempotency_key` CHAR(36) NULL,
                `created_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`, `created_at`),
                KEY `ai_requests_workspace_created_index` (`workspace_id`, `created_at`),
                KEY `ai_requests_model_created_index` (`model_id`, `created_at`),
                KEY `ai_requests_status_created_index` (`status`, `created_at`),
                KEY `ai_requests_subject_index` (`subject_type`, `subject_id`),
                KEY `ai_requests_request_hash_index` (`request_hash`),
                KEY `ai_requests_idempotency_index` (`workspace_id`, `idempotency_key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    // scale: partition candidate by RANGE(created_at) — billions of rows, FK-light, no uuid.
    private function createAiResponses(Database $db): void
    {
        if ($this->hasTable($db, 'ai_responses')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `ai_responses` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `request_id` BIGINT UNSIGNED NOT NULL,
                `workspace_id` BIGINT UNSIGNED NOT NULL,
                `model_id` BIGINT UNSIGNED NULL,
                `finish_reason` VARCHAR(40) NULL,
                `prompt_tokens` INT UNSIGNED NULL,
                `completion_tokens` INT UNSIGNED NULL,
                `total_tokens` INT UNSIGNED NULL,
                `cost_amount` DECIMAL(14,6) NULL,
                `currency_id` BIGINT UNSIGNED NULL,
                `latency_ms` INT UNSIGNED NULL,
                `content` LONGTEXT NULL,
                `usage_raw` JSON NULL,
                `created_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`, `created_at`),
                KEY `ai_responses_request_id_index` (`request_id`),
                KEY `ai_responses_workspace_created_index` (`workspace_id`, `created_at`),
                KEY `ai_responses_model_created_index` (`model_id`, `created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    private function createAiUsage(Database $db): void
    {
        if ($this->hasTable($db, 'ai_usage')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `ai_usage` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `workspace_id` BIGINT UNSIGNED NOT NULL,
                `provider_id` BIGINT UNSIGNED NULL,
                `model_id` BIGINT UNSIGNED NULL,
                `usage_date` DATE NOT NULL,
                `capability` VARCHAR(30) NULL,
                `request_count` BIGINT UNSIGNED NOT NULL DEFAULT 0,
                `success_count` BIGINT UNSIGNED NOT NULL DEFAULT 0,
                `error_count` BIGINT UNSIGNED NOT NULL DEFAULT 0,
                `prompt_tokens` BIGINT UNSIGNED NOT NULL DEFAULT 0,
                `completion_tokens` BIGINT UNSIGNED NOT NULL DEFAULT 0,
                `total_tokens` BIGINT UNSIGNED NOT NULL DEFAULT 0,
                `cost_amount` DECIMAL(16,6) NOT NULL DEFAULT 0,
                `currency_id` BIGINT UNSIGNED NULL,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `ai_usage_uuid_unique` (`uuid`),
                UNIQUE KEY `ai_usage_workspace_model_date_unique` (`workspace_id`, `model_id`, `usage_date`, `capability`),
                KEY `ai_usage_workspace_date_index` (`workspace_id`, `usage_date`),
                KEY `ai_usage_provider_id_index` (`provider_id`),
                KEY `ai_usage_model_id_index` (`model_id`),
                KEY `ai_usage_currency_id_index` (`currency_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    private function createAiCosts(Database $db): void
    {
        if ($this->hasTable($db, 'ai_costs')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `ai_costs` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `model_id` BIGINT UNSIGNED NOT NULL,
                `input_price_per_1k` DECIMAL(12,6) NOT NULL,
                `output_price_per_1k` DECIMAL(12,6) NOT NULL,
                `currency_id` BIGINT UNSIGNED NOT NULL,
                `unit` VARCHAR(20) NOT NULL DEFAULT '1k_tokens',
                `effective_from` DATETIME NOT NULL,
                `effective_to` DATETIME NULL,
                `source` VARCHAR(40) NULL,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `ai_costs_uuid_unique` (`uuid`),
                UNIQUE KEY `ai_costs_model_effective_unique` (`model_id`, `effective_from`),
                KEY `ai_costs_model_id_index` (`model_id`),
                KEY `ai_costs_currency_id_index` (`currency_id`),
                KEY `ai_costs_effective_index` (`effective_from`, `effective_to`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    // scale: partition candidate by RANGE(created_at) — billions of rows, FK-light, no uuid.
    private function createAiLogs(Database $db): void
    {
        if ($this->hasTable($db, 'ai_logs')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `ai_logs` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `workspace_id` BIGINT UNSIGNED NOT NULL,
                `request_id` BIGINT UNSIGNED NULL,
                `level` VARCHAR(20) NOT NULL DEFAULT 'info',
                `event` VARCHAR(60) NOT NULL,
                `provider_id` BIGINT UNSIGNED NULL,
                `model_id` BIGINT UNSIGNED NULL,
                `message` VARCHAR(255) NULL,
                `context` JSON NULL,
                `created_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`, `created_at`),
                KEY `ai_logs_workspace_created_index` (`workspace_id`, `created_at`),
                KEY `ai_logs_request_id_index` (`request_id`),
                KEY `ai_logs_level_created_index` (`level`, `created_at`),
                KEY `ai_logs_event_created_index` (`event`, `created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    // scale: partition candidate by RANGE(created_at) — billions of rows, FK-light, no uuid.
    private function createAiErrors(Database $db): void
    {
        if ($this->hasTable($db, 'ai_errors')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `ai_errors` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `workspace_id` BIGINT UNSIGNED NOT NULL,
                `request_id` BIGINT UNSIGNED NULL,
                `provider_id` BIGINT UNSIGNED NULL,
                `model_id` BIGINT UNSIGNED NULL,
                `error_type` VARCHAR(40) NOT NULL,
                `http_status` SMALLINT UNSIGNED NULL,
                `vendor_code` VARCHAR(80) NULL,
                `message` VARCHAR(255) NULL,
                `detail` JSON NULL,
                `is_retryable` TINYINT(1) NOT NULL DEFAULT 0,
                `created_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`, `created_at`),
                KEY `ai_errors_workspace_created_index` (`workspace_id`, `created_at`),
                KEY `ai_errors_request_id_index` (`request_id`),
                KEY `ai_errors_type_created_index` (`error_type`, `created_at`),
                KEY `ai_errors_provider_created_index` (`provider_id`, `created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    private function createAiCache(Database $db): void
    {
        if ($this->hasTable($db, 'ai_cache')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `ai_cache` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `workspace_id` BIGINT UNSIGNED NOT NULL,
                `cache_key` CHAR(64) NOT NULL,
                `model_id` BIGINT UNSIGNED NULL,
                `capability` VARCHAR(30) NULL,
                `response` LONGTEXT NULL,
                `tokens` INT UNSIGNED NULL,
                `hit_count` INT UNSIGNED NOT NULL DEFAULT 0,
                `last_hit_at` TIMESTAMP NULL DEFAULT NULL,
                `expires_at` DATETIME NOT NULL,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `ai_cache_workspace_key_unique` (`workspace_id`, `cache_key`),
                KEY `ai_cache_workspace_id_index` (`workspace_id`),
                KEY `ai_cache_model_id_index` (`model_id`),
                KEY `ai_cache_expires_at_index` (`expires_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    // -----------------------------------------------------------------
    // Notifications
    // -----------------------------------------------------------------

    // scale: partition candidate by RANGE(created_at) — high volume; FKs documented but added in 0126.
    private function createNotifications(Database $db): void
    {
        if ($this->hasTable($db, 'notifications')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `notifications` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `workspace_id` BIGINT UNSIGNED NULL,
                `user_id` BIGINT UNSIGNED NOT NULL,
                `type_id` BIGINT UNSIGNED NOT NULL,
                `channel_id` BIGINT UNSIGNED NOT NULL,
                `title` VARCHAR(255) NOT NULL,
                `body` TEXT NULL,
                `data` JSON NULL,
                `locale` VARCHAR(10) NULL,
                `actor_id` BIGINT UNSIGNED NULL,
                `subject_type` VARCHAR(60) NULL,
                `subject_id` BIGINT UNSIGNED NULL,
                `read_at` TIMESTAMP NULL DEFAULT NULL,
                `created_at` DATETIME NOT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`, `created_at`),
                UNIQUE KEY `notifications_uuid_unique` (`uuid`),
                KEY `notifications_user_read_index` (`user_id`, `read_at`),
                KEY `notifications_user_created_index` (`user_id`, `created_at`),
                KEY `notifications_workspace_created_index` (`workspace_id`, `created_at`),
                KEY `notifications_type_id_index` (`type_id`),
                KEY `notifications_channel_id_index` (`channel_id`),
                KEY `notifications_actor_id_index` (`actor_id`),
                KEY `notifications_subject_index` (`subject_type`, `subject_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    private function createNotificationTemplates(Database $db): void
    {
        if ($this->hasTable($db, 'notification_templates')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `notification_templates` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `workspace_id` BIGINT UNSIGNED NULL,
                `type_id` BIGINT UNSIGNED NOT NULL,
                `channel_id` BIGINT UNSIGNED NOT NULL,
                `locale` VARCHAR(10) NOT NULL,
                `subject` VARCHAR(255) NULL,
                `body` TEXT NOT NULL,
                `variables` JSON NULL,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `is_system` TINYINT(1) NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                `deleted_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `notification_templates_uuid_unique` (`uuid`),
                UNIQUE KEY `notification_templates_combo_unique` (`workspace_id`, `type_id`, `channel_id`, `locale`),
                KEY `notification_templates_type_id_index` (`type_id`),
                KEY `notification_templates_channel_id_index` (`channel_id`),
                KEY `notification_templates_workspace_id_index` (`workspace_id`),
                KEY `notification_templates_deleted_at_index` (`deleted_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    private function createNotificationChannels(Database $db): void
    {
        if ($this->hasTable($db, 'notification_channels')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `notification_channels` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `key` VARCHAR(40) NOT NULL,
                `name` VARCHAR(120) NOT NULL,
                `driver` VARCHAR(60) NULL,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `is_default` TINYINT(1) NOT NULL DEFAULT 0,
                `requires_address` TINYINT(1) NOT NULL DEFAULT 0,
                `config` JSON NULL,
                `sort_order` INT NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                `deleted_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `notification_channels_uuid_unique` (`uuid`),
                UNIQUE KEY `notification_channels_key_unique` (`key`),
                KEY `notification_channels_active_sort_index` (`is_active`, `sort_order`),
                KEY `notification_channels_deleted_at_index` (`deleted_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    private function createNotificationPreferences(Database $db): void
    {
        if ($this->hasTable($db, 'notification_preferences')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `notification_preferences` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `user_id` BIGINT UNSIGNED NOT NULL,
                `workspace_id` BIGINT UNSIGNED NULL,
                `type_id` BIGINT UNSIGNED NOT NULL,
                `channel_id` BIGINT UNSIGNED NOT NULL,
                `enabled` TINYINT(1) NOT NULL DEFAULT 1,
                `digest` TINYINT(1) NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `notification_preferences_uuid_unique` (`uuid`),
                UNIQUE KEY `notification_preferences_combo_unique` (`user_id`, `workspace_id`, `type_id`, `channel_id`),
                KEY `notification_preferences_user_id_index` (`user_id`),
                KEY `notification_preferences_workspace_id_index` (`workspace_id`),
                KEY `notification_preferences_type_id_index` (`type_id`),
                KEY `notification_preferences_channel_id_index` (`channel_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    private function createNotificationQueue(Database $db): void
    {
        if ($this->hasTable($db, 'notification_queue')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `notification_queue` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `notification_id` BIGINT UNSIGNED NULL,
                `workspace_id` BIGINT UNSIGNED NULL,
                `user_id` BIGINT UNSIGNED NOT NULL,
                `channel_id` BIGINT UNSIGNED NOT NULL,
                `template_id` BIGINT UNSIGNED NULL,
                `type_id` BIGINT UNSIGNED NULL,
                `payload` JSON NULL,
                `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
                `priority` TINYINT UNSIGNED NOT NULL DEFAULT 5,
                `attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
                `max_attempts` TINYINT UNSIGNED NOT NULL DEFAULT 3,
                `available_at` DATETIME NULL,
                `scheduled_at` DATETIME NULL,
                `reserved_at` TIMESTAMP NULL DEFAULT NULL,
                `sent_at` TIMESTAMP NULL DEFAULT NULL,
                `failed_at` TIMESTAMP NULL DEFAULT NULL,
                `idempotency_key` CHAR(36) NULL,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `notification_queue_uuid_unique` (`uuid`),
                UNIQUE KEY `notification_queue_idempotency_unique` (`idempotency_key`),
                KEY `notification_queue_status_available_index` (`status`, `available_at`, `priority`),
                KEY `notification_queue_scheduled_index` (`scheduled_at`),
                KEY `notification_queue_notification_id_index` (`notification_id`),
                KEY `notification_queue_workspace_id_index` (`workspace_id`),
                KEY `notification_queue_user_id_index` (`user_id`),
                KEY `notification_queue_channel_id_index` (`channel_id`),
                KEY `notification_queue_template_id_index` (`template_id`),
                KEY `notification_queue_type_id_index` (`type_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    // scale: partition candidate by RANGE(created_at) — high volume, FK-light, no uuid.
    private function createNotificationLogs(Database $db): void
    {
        if ($this->hasTable($db, 'notification_logs')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `notification_logs` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `notification_id` BIGINT UNSIGNED NULL,
                `queue_id` BIGINT UNSIGNED NULL,
                `workspace_id` BIGINT UNSIGNED NULL,
                `user_id` BIGINT UNSIGNED NULL,
                `channel_id` BIGINT UNSIGNED NOT NULL,
                `status` VARCHAR(20) NOT NULL,
                `attempt` TINYINT UNSIGNED NOT NULL DEFAULT 1,
                `provider` VARCHAR(60) NULL,
                `provider_message_id` VARCHAR(120) NULL,
                `error_code` VARCHAR(80) NULL,
                `response` JSON NULL,
                `latency_ms` INT UNSIGNED NULL,
                `created_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`, `created_at`),
                KEY `notification_logs_notification_id_index` (`notification_id`),
                KEY `notification_logs_queue_id_index` (`queue_id`),
                KEY `notification_logs_workspace_created_index` (`workspace_id`, `created_at`),
                KEY `notification_logs_channel_status_index` (`channel_id`, `status`, `created_at`),
                KEY `notification_logs_provider_message_index` (`provider_message_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    // -----------------------------------------------------------------
    // Seeding — small system catalogs (workspace_id NULL / is_system scope)
    // -----------------------------------------------------------------

    private function seedAiProviders(Database $db): void
    {
        if (! $this->hasTable($db, 'ai_providers')) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        foreach ($this->providers as [$key, $name, $slug, $capabilities, $authType, $requiredFields, $sort]) {
            if ($db->table('ai_providers')->where('key', '=', $key)->exists()) {
                continue;
            }
            $db->table('ai_providers')->insert([
                'uuid'            => $this->uuid($db),
                'key'             => $key,
                'name'            => $name,
                'slug'            => $slug,
                'capabilities'    => $capabilities,
                'auth_type'       => $authType,
                'required_fields' => $requiredFields,
                'is_active'       => 1,
                'sort_order'      => $sort,
                'created_at'      => $now,
                'updated_at'      => $now,
            ]);
        }
    }

    /**
     * Seed one sensible default model per provider so the multi-engine catalog is
     * usable out of the box. Pricing is intentionally left NULL here (current
     * prices are maintained via ai_costs / vendor sync, not hard-coded).
     */
    private function seedAiModels(Database $db): void
    {
        if (! $this->hasTable($db, 'ai_models') || ! $this->hasTable($db, 'ai_providers')) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        // provider key => [model key, name, capability, supports_streaming, supports_vision, is_default]
        $models = [
            'openai'       => ['gpt-4o', 'GPT-4o', 'chat', 1, 1, 1],
            'anthropic'    => ['claude-3-7-sonnet', 'Claude 3.7 Sonnet', 'chat', 1, 1, 1],
            'gemini'       => ['gemini-1.5-pro', 'Gemini 1.5 Pro', 'chat', 1, 1, 1],
            'deepseek'     => ['deepseek-chat', 'DeepSeek Chat', 'chat', 1, 0, 1],
            'azure_openai' => ['gpt-4o', 'Azure GPT-4o', 'chat', 1, 1, 1],
            'heygen'       => ['heygen-avatar', 'HeyGen Avatar', 'video', 0, 0, 1],
        ];
        foreach ($models as $providerKey => [$modelKey, $name, $capability, $streaming, $vision, $isDefault]) {
            $providerId = (int) $db->table('ai_providers')->where('key', '=', $providerKey)->value('id');
            if ($providerId <= 0) {
                continue;
            }
            $exists = $db->table('ai_models')
                ->where('provider_id', '=', $providerId)
                ->where('key', '=', $modelKey)
                ->exists();
            if ($exists) {
                continue;
            }
            $db->table('ai_models')->insert([
                'uuid'               => $this->uuid($db),
                'provider_id'        => $providerId,
                'key'                => $modelKey,
                'name'               => $name,
                'capability'         => $capability,
                'supports_streaming' => $streaming,
                'supports_vision'    => $vision,
                'is_active'          => 1,
                'is_default'         => $isDefault,
                'sort_order'         => 0,
                'created_at'         => $now,
                'updated_at'         => $now,
            ]);
        }
    }

    private function seedNotificationChannels(Database $db): void
    {
        if (! $this->hasTable($db, 'notification_channels')) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        foreach ($this->channels as [$key, $name, $driver, $requiresAddress, $isDefault, $sort]) {
            if ($db->table('notification_channels')->where('key', '=', $key)->exists()) {
                continue;
            }
            $db->table('notification_channels')->insert([
                'uuid'             => $this->uuid($db),
                'key'              => $key,
                'name'             => $name,
                'driver'           => $driver,
                'is_active'        => 1,
                'is_default'       => $isDefault,
                'requires_address' => $requiresAddress,
                'sort_order'       => $sort,
                'created_at'       => $now,
                'updated_at'       => $now,
            ]);
        }
    }

    // -----------------------------------------------------------------
    // Idempotency helpers
    // -----------------------------------------------------------------

    private function uuid(Database $db): string
    {
        return (string) $db->scalar('SELECT UUID()');
    }

    private function hasTable(Database $db, string $table): bool
    {
        return (int) $db->scalar(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [$table]
        ) > 0;
    }

    private function hasColumn(Database $db, string $table, string $column): bool
    {
        return (int) $db->scalar(
            'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        ) > 0;
    }

    private function hasConstraint(Database $db, string $table, string $constraint): bool
    {
        return (int) $db->scalar(
            'SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?',
            [$table, $constraint]
        ) > 0;
    }

    private function hasIndex(Database $db, string $table, string $index): bool
    {
        return (int) $db->scalar(
            'SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
            [$table, $index]
        ) > 0;
    }
};
