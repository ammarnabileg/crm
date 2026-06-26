<?php

declare(strict_types=1);

use App\Core\Database;
use Database\Migration;

/**
 * D2 — Workspaces & Settings (CREATE) — docs/database/03-Workspaces-Settings.md.
 *
 * Creates the dedicated tables that decompose the early `workspaces.settings`
 * JSON/columns plus the non-tenant configuration tables that live beside
 * workspaces:
 *   - workspace_settings   (per-tenant key/value — generalizes BUILT `settings`)
 *   - workspace_branding   (1:1 white-label visual identity)
 *   - workspace_billing    (1:1 legal/billing identity printed on invoices)
 *   - workspace_ai_settings(1:1 per-tenant AI *preferences*; secrets stay in D8)
 *   - workspace_storage    (1:1 storage provider binding + quota)
 *   - workspace_integrations (marketplace installs per tenant)
 *   - workspace_domains    (custom domains / career sites)
 *   - workspace_invitations(pending invites -> become D1 memberships)
 *   - global_settings      (platform-wide key/value, NOT tenant-scoped)
 *   - user_settings        (per-user prefs, optional workspace context)
 *   - mail_settings        (outbound mail; workspace_id NULL = platform default)
 *
 * This file is STRUCTURE ONLY: every CREATE includes its PK, uuid UNIQUE,
 * business UNIQUE composites and an index on every FK column, but NO FOREIGN KEY
 * constraints (those live in 0120_fk_workspace_settings.php so create-order and
 * cross-domain cycles never break the build). Idempotent via information_schema
 * guards. The platform-default `mail_settings` row (workspace_id NULL) is seeded.
 *
 * BUILT and NOT created here: `workspaces`, `settings`. Per-entity status /
 * catalog tables this domain references (`workspace_statuses`,
 * `integration_status`, `domain_status`, `invitation_status`, `integrations`)
 * are intentionally outside this file's explicit table set; their FK columns +
 * indexes are present and the constraints are added (hasTable-guarded) in the FK
 * file so they activate when those tables exist.
 */
return new class extends Migration {
    public function up(Database $db): void
    {
        $this->createWorkspaceSettings($db);
        $this->createWorkspaceBranding($db);
        $this->createWorkspaceBilling($db);
        $this->createWorkspaceAiSettings($db);
        $this->createWorkspaceStorage($db);
        $this->createWorkspaceIntegrations($db);
        $this->createWorkspaceDomains($db);
        $this->createWorkspaceInvitations($db);
        $this->createGlobalSettings($db);
        $this->createUserSettings($db);
        $this->createMailSettings($db);

        // System defaults (idempotent): the platform-fallback mail config row.
        $this->seedPlatformMailSettings($db);
    }

    public function down(Database $db): void
    {
        foreach ([
            'mail_settings',
            'user_settings',
            'global_settings',
            'workspace_invitations',
            'workspace_domains',
            'workspace_integrations',
            'workspace_storage',
            'workspace_ai_settings',
            'workspace_billing',
            'workspace_branding',
            'workspace_settings',
        ] as $table) {
            $db->unprepared("DROP TABLE IF EXISTS `{$table}`");
        }
    }

    // 9.3 workspace_settings — per-tenant key/value preferences (no soft-delete).
    private function createWorkspaceSettings(Database $db): void
    {
        if ($this->hasTable($db, 'workspace_settings')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `workspace_settings` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `workspace_id` BIGINT UNSIGNED NOT NULL,
                `group` VARCHAR(60) NULL,
                `key` VARCHAR(120) NOT NULL,
                `value` TEXT NULL,
                `type` VARCHAR(20) NOT NULL DEFAULT 'string',
                `is_public` TINYINT(1) NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `workspace_settings_uuid_unique` (`uuid`),
                UNIQUE KEY `workspace_settings_workspace_key_unique` (`workspace_id`, `key`),
                KEY `workspace_settings_workspace_group_index` (`workspace_id`, `group`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    // 9.4 workspace_branding — 1:1 white-label visual identity (no soft-delete).
    private function createWorkspaceBranding(Database $db): void
    {
        if ($this->hasTable($db, 'workspace_branding')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `workspace_branding` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `workspace_id` BIGINT UNSIGNED NOT NULL,
                `logo_file_id` BIGINT UNSIGNED NULL,
                `logo_dark_file_id` BIGINT UNSIGNED NULL,
                `favicon_file_id` BIGINT UNSIGNED NULL,
                `primary_color` VARCHAR(20) NULL,
                `secondary_color` VARCHAR(20) NULL,
                `accent_color` VARCHAR(20) NULL,
                `theme` VARCHAR(40) NULL,
                `custom_css` LONGTEXT NULL,
                `email_header_html` LONGTEXT NULL,
                `email_footer_html` LONGTEXT NULL,
                `is_white_label` TINYINT(1) NOT NULL DEFAULT 0,
                `show_powered_by` TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `workspace_branding_uuid_unique` (`uuid`),
                UNIQUE KEY `workspace_branding_workspace_id_unique` (`workspace_id`),
                KEY `workspace_branding_logo_file_id_index` (`logo_file_id`),
                KEY `workspace_branding_logo_dark_file_id_index` (`logo_dark_file_id`),
                KEY `workspace_branding_favicon_file_id_index` (`favicon_file_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    // 9.5 workspace_billing — 1:1 legal/billing identity (no soft-delete).
    private function createWorkspaceBilling(Database $db): void
    {
        if ($this->hasTable($db, 'workspace_billing')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `workspace_billing` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `workspace_id` BIGINT UNSIGNED NOT NULL,
                `legal_name` VARCHAR(200) NULL,
                `tax_id` VARCHAR(60) NULL,
                `vat_number` VARCHAR(60) NULL,
                `registration_number` VARCHAR(60) NULL,
                `billing_email` VARCHAR(190) NULL,
                `billing_phone` VARCHAR(40) NULL,
                `address_line1` VARCHAR(200) NULL,
                `address_line2` VARCHAR(200) NULL,
                `city` VARCHAR(120) NULL,
                `state` VARCHAR(120) NULL,
                `postal_code` VARCHAR(30) NULL,
                `country_id` BIGINT UNSIGNED NULL,
                `currency_id` BIGINT UNSIGNED NULL,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `workspace_billing_uuid_unique` (`uuid`),
                UNIQUE KEY `workspace_billing_workspace_id_unique` (`workspace_id`),
                KEY `workspace_billing_country_id_index` (`country_id`),
                KEY `workspace_billing_currency_id_index` (`currency_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    // 9.6 workspace_ai_settings — 1:1 AI *preferences* (secrets stay in D8).
    private function createWorkspaceAiSettings(Database $db): void
    {
        if ($this->hasTable($db, 'workspace_ai_settings')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `workspace_ai_settings` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `workspace_id` BIGINT UNSIGNED NOT NULL,
                `default_provider_id` BIGINT UNSIGNED NULL,
                `default_model_id` BIGINT UNSIGNED NULL,
                `default_key_id` BIGINT UNSIGNED NULL,
                `is_ai_enabled` TINYINT(1) NOT NULL DEFAULT 1,
                `auto_screen_enabled` TINYINT(1) NOT NULL DEFAULT 0,
                `auto_interview_enabled` TINYINT(1) NOT NULL DEFAULT 0,
                `temperature` DECIMAL(3,2) NULL,
                `monthly_token_cap` BIGINT UNSIGNED NULL,
                `monthly_cost_cap` DECIMAL(12,2) NULL,
                `cost_currency_id` BIGINT UNSIGNED NULL,
                `preferences` JSON NULL,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `workspace_ai_settings_uuid_unique` (`uuid`),
                UNIQUE KEY `workspace_ai_settings_workspace_id_unique` (`workspace_id`),
                KEY `workspace_ai_settings_default_provider_id_index` (`default_provider_id`),
                KEY `workspace_ai_settings_default_model_id_index` (`default_model_id`),
                KEY `workspace_ai_settings_default_key_id_index` (`default_key_id`),
                KEY `workspace_ai_settings_cost_currency_id_index` (`cost_currency_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    // 9.7 workspace_storage — 1:1 storage provider binding + quota.
    private function createWorkspaceStorage(Database $db): void
    {
        if ($this->hasTable($db, 'workspace_storage')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `workspace_storage` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `workspace_id` BIGINT UNSIGNED NOT NULL,
                `storage_provider_id` BIGINT UNSIGNED NOT NULL,
                `bucket` VARCHAR(190) NULL,
                `region` VARCHAR(60) NULL,
                `path_prefix` VARCHAR(190) NULL,
                `credentials` TEXT NULL,
                `quota_bytes` BIGINT UNSIGNED NULL,
                `used_bytes` BIGINT UNSIGNED NOT NULL DEFAULT 0,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `workspace_storage_uuid_unique` (`uuid`),
                UNIQUE KEY `workspace_storage_workspace_id_unique` (`workspace_id`),
                KEY `workspace_storage_storage_provider_id_index` (`storage_provider_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    // 9.8 workspace_integrations — marketplace installs per tenant (soft-delete).
    private function createWorkspaceIntegrations(Database $db): void
    {
        if ($this->hasTable($db, 'workspace_integrations')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `workspace_integrations` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `workspace_id` BIGINT UNSIGNED NOT NULL,
                `integration_id` BIGINT UNSIGNED NOT NULL,
                `integration_status_id` BIGINT UNSIGNED NOT NULL,
                `config` JSON NULL,
                `credentials` TEXT NULL,
                `external_account_id` VARCHAR(190) NULL,
                `installed_by` BIGINT UNSIGNED NULL,
                `connected_at` TIMESTAMP NULL DEFAULT NULL,
                `last_synced_at` TIMESTAMP NULL DEFAULT NULL,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                `deleted_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `workspace_integrations_uuid_unique` (`uuid`),
                UNIQUE KEY `workspace_integrations_workspace_integration_unique` (`workspace_id`, `integration_id`),
                KEY `workspace_integrations_integration_id_index` (`integration_id`),
                KEY `workspace_integrations_integration_status_id_index` (`integration_status_id`),
                KEY `workspace_integrations_installed_by_index` (`installed_by`),
                KEY `workspace_integrations_workspace_status_index` (`workspace_id`, `integration_status_id`),
                KEY `workspace_integrations_deleted_at_index` (`deleted_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    // 9.9 workspace_domains — custom domains / career sites (soft-delete).
    private function createWorkspaceDomains(Database $db): void
    {
        if ($this->hasTable($db, 'workspace_domains')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `workspace_domains` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `workspace_id` BIGINT UNSIGNED NOT NULL,
                `hostname` VARCHAR(255) NOT NULL,
                `domain_type_id` BIGINT UNSIGNED NULL,
                `domain_status_id` BIGINT UNSIGNED NOT NULL,
                `verification_token` VARCHAR(190) NULL,
                `verification_method` VARCHAR(20) NULL,
                `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
                `ssl_enabled` TINYINT(1) NOT NULL DEFAULT 0,
                `ssl_expires_at` TIMESTAMP NULL DEFAULT NULL,
                `verified_at` TIMESTAMP NULL DEFAULT NULL,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                `deleted_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `workspace_domains_uuid_unique` (`uuid`),
                UNIQUE KEY `workspace_domains_hostname_unique` (`hostname`),
                KEY `workspace_domains_workspace_id_index` (`workspace_id`),
                KEY `workspace_domains_domain_type_id_index` (`domain_type_id`),
                KEY `workspace_domains_domain_status_id_index` (`domain_status_id`),
                KEY `workspace_domains_workspace_primary_index` (`workspace_id`, `is_primary`),
                KEY `workspace_domains_deleted_at_index` (`deleted_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    // 9.10 workspace_invitations — pending invites (soft-delete).
    private function createWorkspaceInvitations(Database $db): void
    {
        if ($this->hasTable($db, 'workspace_invitations')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `workspace_invitations` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `workspace_id` BIGINT UNSIGNED NOT NULL,
                `email` VARCHAR(190) NOT NULL,
                `role_id` BIGINT UNSIGNED NOT NULL,
                `invited_by` BIGINT UNSIGNED NULL,
                `user_id` BIGINT UNSIGNED NULL,
                `invitation_status_id` BIGINT UNSIGNED NOT NULL,
                `token` VARCHAR(190) NOT NULL,
                `message` VARCHAR(500) NULL,
                `expires_at` TIMESTAMP NOT NULL,
                `accepted_at` TIMESTAMP NULL DEFAULT NULL,
                `membership_id` BIGINT UNSIGNED NULL,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                `deleted_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `workspace_invitations_uuid_unique` (`uuid`),
                UNIQUE KEY `workspace_invitations_token_unique` (`token`),
                UNIQUE KEY `workspace_invitations_workspace_email_unique` (`workspace_id`, `email`),
                KEY `workspace_invitations_role_id_index` (`role_id`),
                KEY `workspace_invitations_invited_by_index` (`invited_by`),
                KEY `workspace_invitations_user_id_index` (`user_id`),
                KEY `workspace_invitations_invitation_status_id_index` (`invitation_status_id`),
                KEY `workspace_invitations_membership_id_index` (`membership_id`),
                KEY `workspace_invitations_workspace_status_index` (`workspace_id`, `invitation_status_id`),
                KEY `workspace_invitations_expires_at_index` (`expires_at`),
                KEY `workspace_invitations_deleted_at_index` (`deleted_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    // 9.11 global_settings — platform-wide key/value (NOT tenant-scoped).
    private function createGlobalSettings(Database $db): void
    {
        if ($this->hasTable($db, 'global_settings')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `global_settings` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `group` VARCHAR(60) NULL,
                `key` VARCHAR(120) NOT NULL,
                `value` TEXT NULL,
                `type` VARCHAR(20) NOT NULL DEFAULT 'string',
                `is_public` TINYINT(1) NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `global_settings_uuid_unique` (`uuid`),
                UNIQUE KEY `global_settings_key_unique` (`key`),
                KEY `global_settings_group_index` (`group`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    // 9.12 user_settings — per-user prefs, optional workspace context.
    private function createUserSettings(Database $db): void
    {
        if ($this->hasTable($db, 'user_settings')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `user_settings` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `user_id` BIGINT UNSIGNED NOT NULL,
                `workspace_id` BIGINT UNSIGNED NULL,
                `group` VARCHAR(60) NULL,
                `key` VARCHAR(120) NOT NULL,
                `value` TEXT NULL,
                `type` VARCHAR(20) NOT NULL DEFAULT 'string',
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `user_settings_uuid_unique` (`uuid`),
                UNIQUE KEY `user_settings_user_workspace_key_unique` (`user_id`, `workspace_id`, `key`),
                KEY `user_settings_workspace_id_index` (`workspace_id`),
                KEY `user_settings_user_group_index` (`user_id`, `group`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    // 9.13 mail_settings — outbound mail; workspace_id NULL = platform default.
    private function createMailSettings(Database $db): void
    {
        if ($this->hasTable($db, 'mail_settings')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `mail_settings` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `workspace_id` BIGINT UNSIGNED NULL,
                `mailer` VARCHAR(40) NOT NULL DEFAULT 'smtp',
                `host` VARCHAR(190) NULL,
                `port` SMALLINT UNSIGNED NULL,
                `username` VARCHAR(190) NULL,
                `password` TEXT NULL,
                `encryption` VARCHAR(10) NULL,
                `api_key` TEXT NULL,
                `from_name` VARCHAR(150) NULL,
                `from_email` VARCHAR(190) NULL,
                `reply_to` VARCHAR(190) NULL,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `is_verified` TINYINT(1) NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `mail_settings_uuid_unique` (`uuid`),
                UNIQUE KEY `mail_settings_workspace_id_unique` (`workspace_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    /**
     * Seed the platform-fallback mail config (workspace_id NULL). System scope:
     * the single platform default row the doc specifies (§9.13). Idempotent.
     */
    private function seedPlatformMailSettings(Database $db): void
    {
        if (! $this->hasTable($db, 'mail_settings')) {
            return;
        }
        $exists = (int) $db->scalar(
            'SELECT COUNT(*) FROM `mail_settings` WHERE `workspace_id` IS NULL'
        ) > 0;
        if ($exists) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $db->table('mail_settings')->insert([
            'uuid'        => $this->uuid($db),
            'workspace_id' => null,
            'mailer'      => 'smtp',
            'is_active'   => 1,
            'is_verified' => 0,
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);
    }

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
