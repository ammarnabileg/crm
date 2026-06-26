<?php

declare(strict_types=1);

use App\Core\Database;
use Database\Migration;

/**
 * D3 — Authentication (docs/database/04-Authentication.md).
 *
 * Creates the authentication / session / credential-security tables for HalaOps:
 *  - `sessions`               — server-side session store (active-sessions screen).
 *  - `remember_tokens`        — persistent "remember me" logins (split-token).
 *  - `login_histories`        — append-only login-outcome audit (higher volume).
 *  - `devices`                — known / trusted devices per user.
 *  - `failed_login_attempts`  — brute-force throttling / lockout counters.
 *  - `mfa_methods`            — enrolled MFA factors (encrypted TOTP secret).
 *  - `mfa_recovery_codes`     — single-use hashed MFA backup codes.
 *  - `personal_access_tokens` — hashed API tokens (optional workspace scope).
 *
 * SKIPPED (already BUILT): `password_resets` (0012), `onboarding_progress` (0014).
 *
 * Domain invariants honored here (Bible + domain doc):
 *  - Authentication is user-level: these tables are NOT tenant-scoped and carry
 *    no `workspace_id` — the single exception is `personal_access_tokens`, which
 *    has a NULLABLE `workspace_id` (NULL = user-global token).
 *  - No soft-deletes anywhere in this domain (ephemeral/append rows are pruned
 *    by expiry / last_activity / age) — so NO `deleted_at` columns.
 *  - `uuid` is OMITTED on the ephemeral/append tables the doc exempts
 *    (`sessions`, `login_histories`, `failed_login_attempts`).
 *  - Configuration-driven type/reason columns are FK -> `lookup_values`
 *    (`device_type`, `mfa_method_type`, `login_failure_reason`) — never ENUM.
 *
 * STRUCTURE + ALL INDEXES live here; NO FOREIGN KEY constraints (those are added
 * in 0121_fk_authentication.php). No system-default rows to seed (this domain has
 * no `*_statuses` / catalog tables; the type/reason lists are centralized D0
 * `lookup_values`). Idempotent via information_schema guards.
 */
return new class extends Migration {
    public function up(Database $db): void
    {
        $this->createDevices($db);
        $this->createSessions($db);
        $this->createRememberTokens($db);
        $this->createLoginHistories($db);
        $this->createFailedLoginAttempts($db);
        $this->createMfaMethods($db);
        $this->createMfaRecoveryCodes($db);
        $this->createPersonalAccessTokens($db);
    }

    public function down(Database $db): void
    {
        // Drop in reverse dependency order (children before parents); FKs are
        // dropped first by 0121's down(), so plain DROP TABLE is safe here.
        foreach ([
            'personal_access_tokens',
            'mfa_recovery_codes',
            'mfa_methods',
            'failed_login_attempts',
            'login_histories',
            'remember_tokens',
            'sessions',
            'devices',
        ] as $table) {
            $db->unprepared("DROP TABLE IF EXISTS `{$table}`");
        }
    }

    /**
     * 5. `devices` — known / trusted devices per user. Created first because
     * `sessions`, `remember_tokens`, and `login_histories` reference it.
     */
    private function createDevices(Database $db): void
    {
        if ($this->hasTable($db, 'devices')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `devices` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `user_id` BIGINT UNSIGNED NOT NULL,
                `device_hash` VARCHAR(128) NOT NULL,
                `name` VARCHAR(190) NULL,
                `type_id` BIGINT UNSIGNED NULL,
                `platform` VARCHAR(60) NULL,
                `ip_address` VARCHAR(45) NULL,
                `is_trusted` TINYINT(1) NOT NULL DEFAULT 0,
                `trusted_at` TIMESTAMP NULL DEFAULT NULL,
                `last_used_at` TIMESTAMP NULL DEFAULT NULL,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `devices_uuid_unique` (`uuid`),
                UNIQUE KEY `devices_user_device_hash_unique` (`user_id`, `device_hash`),
                KEY `devices_user_id_index` (`user_id`),
                KEY `devices_type_id_index` (`type_id`),
                KEY `devices_is_trusted_index` (`is_trusted`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    /**
     * 1. `sessions` — server-side session store. PK is the opaque session id
     * (VARCHAR), not a BIGINT surrogate; no `uuid` (the id IS the public handle,
     * ephemeral table, Bible §1 exemption). Tenant lives inside `payload`.
     */
    private function createSessions(Database $db): void
    {
        if ($this->hasTable($db, 'sessions')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `sessions` (
                `id` VARCHAR(128) NOT NULL,
                `user_id` BIGINT UNSIGNED NULL,
                `device_id` BIGINT UNSIGNED NULL,
                `ip_address` VARCHAR(45) NULL,
                `user_agent` VARCHAR(512) NULL,
                `device_label` VARCHAR(190) NULL,
                `last_activity` INT UNSIGNED NOT NULL,
                `payload` LONGTEXT NOT NULL,
                PRIMARY KEY (`id`),
                KEY `sessions_user_id_index` (`user_id`),
                KEY `sessions_device_id_index` (`device_id`),
                KEY `sessions_last_activity_index` (`last_activity`),
                KEY `sessions_user_last_activity_index` (`user_id`, `last_activity`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    /**
     * 2. `remember_tokens` — persistent-login tokens (split-token: public
     * `selector` + hashed validator). Stores only the hash of the secret half.
     */
    private function createRememberTokens(Database $db): void
    {
        if ($this->hasTable($db, 'remember_tokens')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `remember_tokens` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `user_id` BIGINT UNSIGNED NOT NULL,
                `device_id` BIGINT UNSIGNED NULL,
                `selector` VARCHAR(64) NOT NULL,
                `token_hash` VARCHAR(255) NOT NULL,
                `ip_address` VARCHAR(45) NULL,
                `user_agent` VARCHAR(512) NULL,
                `last_used_at` TIMESTAMP NULL DEFAULT NULL,
                `expires_at` TIMESTAMP NOT NULL,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `remember_tokens_uuid_unique` (`uuid`),
                UNIQUE KEY `remember_tokens_selector_unique` (`selector`),
                KEY `remember_tokens_user_id_index` (`user_id`),
                KEY `remember_tokens_device_id_index` (`device_id`),
                KEY `remember_tokens_expires_at_index` (`expires_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    /**
     * 4. `login_histories` — append-only login-outcome log. Higher-ish volume:
     * narrow rows, NO `uuid` (Bible §1 exemption), indexed by (user_id,
     * created_at). FKs are kept (volume is below the billions-scale tables) but
     * the table is a RANGE-partition / archival candidate.
     */
    private function createLoginHistories(Database $db): void
    {
        if ($this->hasTable($db, 'login_histories')) {
            return;
        }
        // scale: partition candidate by RANGE(created_at) (monthly) + age archival.
        $db->unprepared(
            "CREATE TABLE `login_histories` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `user_id` BIGINT UNSIGNED NULL,
                `device_id` BIGINT UNSIGNED NULL,
                `email_attempted` VARCHAR(190) NULL,
                `successful` TINYINT(1) NOT NULL DEFAULT 0,
                `failure_reason_id` BIGINT UNSIGNED NULL,
                `mfa_used` TINYINT(1) NOT NULL DEFAULT 0,
                `ip_address` VARCHAR(45) NOT NULL,
                `user_agent` VARCHAR(512) NULL,
                `country_id` BIGINT UNSIGNED NULL,
                `location_label` VARCHAR(190) NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `login_histories_user_created_index` (`user_id`, `created_at`),
                KEY `login_histories_ip_created_index` (`ip_address`, `created_at`),
                KEY `login_histories_successful_index` (`successful`),
                KEY `login_histories_device_id_index` (`device_id`),
                KEY `login_histories_failure_reason_id_index` (`failure_reason_id`),
                KEY `login_histories_country_id_index` (`country_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    /**
     * 6. `failed_login_attempts` — brute-force throttling counters, one row per
     * throttle bucket (`throttle_key`). NO `uuid` (internal ephemeral table).
     * NO foreign keys (keyed by email/ip, not user_id — intentional, see doc).
     */
    private function createFailedLoginAttempts(Database $db): void
    {
        if ($this->hasTable($db, 'failed_login_attempts')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `failed_login_attempts` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `throttle_key` VARCHAR(190) NOT NULL,
                `email` VARCHAR(190) NULL,
                `ip_address` VARCHAR(45) NOT NULL,
                `attempts` INT UNSIGNED NOT NULL DEFAULT 0,
                `last_attempt_at` TIMESTAMP NULL DEFAULT NULL,
                `available_at` TIMESTAMP NULL DEFAULT NULL,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `failed_login_attempts_throttle_key_unique` (`throttle_key`),
                KEY `failed_login_attempts_ip_address_index` (`ip_address`),
                KEY `failed_login_attempts_available_at_index` (`available_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    /**
     * 7. `mfa_methods` — enrolled MFA factors. `secret` is APPLICATION-ENCRYPTED
     * (reversible) TOTP secret — the one credential here that is encrypted, not
     * hashed. `type_id` is a config-driven lookup (mfa_method_type), not an ENUM.
     */
    private function createMfaMethods(Database $db): void
    {
        if ($this->hasTable($db, 'mfa_methods')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `mfa_methods` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `user_id` BIGINT UNSIGNED NOT NULL,
                `type_id` BIGINT UNSIGNED NOT NULL,
                `label` VARCHAR(120) NULL,
                `secret` TEXT NULL,
                `destination` VARCHAR(190) NULL,
                `is_enabled` TINYINT(1) NOT NULL DEFAULT 0,
                `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
                `confirmed_at` TIMESTAMP NULL DEFAULT NULL,
                `last_used_at` TIMESTAMP NULL DEFAULT NULL,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `mfa_methods_uuid_unique` (`uuid`),
                KEY `mfa_methods_user_id_index` (`user_id`),
                KEY `mfa_methods_type_id_index` (`type_id`),
                KEY `mfa_methods_user_enabled_index` (`user_id`, `is_enabled`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    /**
     * 8. `mfa_recovery_codes` — single-use hashed backup codes. `code_hash` is
     * unique within a user's set. CASCADE from both users and mfa_methods.
     */
    private function createMfaRecoveryCodes(Database $db): void
    {
        if ($this->hasTable($db, 'mfa_recovery_codes')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `mfa_recovery_codes` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `user_id` BIGINT UNSIGNED NOT NULL,
                `mfa_method_id` BIGINT UNSIGNED NULL,
                `code_hash` VARCHAR(255) NOT NULL,
                `used_at` TIMESTAMP NULL DEFAULT NULL,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `mfa_recovery_codes_uuid_unique` (`uuid`),
                UNIQUE KEY `mfa_recovery_codes_user_code_unique` (`user_id`, `code_hash`),
                KEY `mfa_recovery_codes_user_id_index` (`user_id`),
                KEY `mfa_recovery_codes_mfa_method_id_index` (`mfa_method_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    /**
     * 9. `personal_access_tokens` — hashed API tokens. The only table in this
     * domain that MAY be tenant-scoped: NULLABLE `workspace_id` (NULL =
     * user-global). `token_hash` is UNIQUE (presented token -> at most one row).
     */
    private function createPersonalAccessTokens(Database $db): void
    {
        if ($this->hasTable($db, 'personal_access_tokens')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `personal_access_tokens` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `user_id` BIGINT UNSIGNED NOT NULL,
                `workspace_id` BIGINT UNSIGNED NULL,
                `name` VARCHAR(190) NOT NULL,
                `token_hash` VARCHAR(64) NOT NULL,
                `abilities` JSON NULL,
                `last_used_at` TIMESTAMP NULL DEFAULT NULL,
                `expires_at` TIMESTAMP NULL DEFAULT NULL,
                `revoked_at` TIMESTAMP NULL DEFAULT NULL,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `personal_access_tokens_uuid_unique` (`uuid`),
                UNIQUE KEY `personal_access_tokens_token_hash_unique` (`token_hash`),
                KEY `personal_access_tokens_user_id_index` (`user_id`),
                KEY `personal_access_tokens_workspace_id_index` (`workspace_id`),
                KEY `personal_access_tokens_user_workspace_index` (`user_id`, `workspace_id`),
                KEY `personal_access_tokens_expires_at_index` (`expires_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
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
