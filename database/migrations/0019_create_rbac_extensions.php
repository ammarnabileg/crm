<?php

declare(strict_types=1);

use App\Core\Database;
use Database\Migration;

/**
 * D1 — RBAC & Membership: blueprint extensions (CREATE / structure only).
 *
 * Creates the NEW tables that extend the BUILT RBAC core (roles, permissions,
 * memberships, and the built pivots permission_role / membership_role / user_role
 * shipped in 0003–0008/0016). See docs/database/02-RBAC-Membership.md.
 *
 * Tables created here:
 *  - `policies`             conditional / scoped (ABAC) authorization layer
 *  - `policy_permissions`   pivot: which permissions a policy governs
 *  - `permission_caches`    materialized effective permission set per principal
 *  - `role_histories`       append-only grant/revoke trail for role assignments
 *  - `permission_histories` append-only grant/revoke trail for permission grants
 *
 * Two-file pattern: this file owns COLUMNS + ALL INDEXES only — NO foreign key
 * constraints. Every FK (intra-domain + cross-domain to the BUILT roles /
 * permissions / memberships / workspaces / users and to lookup_values from 0018)
 * is added in the paired FK migration 0119_fk_rbac_extensions.php. Idempotent via
 * information_schema guards.
 *
 * Config-driven (no ENUM): `policies.effect` and the two *_histories `action`
 * fields reference `lookup_values` (categories `policy_effect`,
 * `role_history_action`, `permission_history_action`) via *_id FK columns; the
 * denormalized string copies are fast-filter mirrors, never ENUMs. The lookup
 * VALUES themselves are seeded centrally (DatabaseSeeder), not here.
 */
return new class extends Migration {
    public function up(Database $db): void
    {
        $this->createPolicies($db);
        $this->createPolicyPermissions($db);
        $this->createPermissionCaches($db);
        $this->createRoleHistories($db);
        $this->createPermissionHistories($db);
        // No system-default rows to seed: this domain has no *_statuses / catalog
        // tables, and lookup_values (policy_effect / *_history_action) are seeded
        // centrally per the build brief.
    }

    public function down(Database $db): void
    {
        foreach ([
            'permission_histories',
            'role_histories',
            'permission_caches',
            'policy_permissions',
            'policies',
        ] as $table) {
            $db->unprepared("DROP TABLE IF EXISTS `{$table}`");
        }
    }

    /**
     * `policies` — conditional / scoped (allow/deny + JSON conditions) layer.
     * Soft-delete = Yes. Polymorphic attach via subject_type/subject_id (no FK).
     */
    private function createPolicies(Database $db): void
    {
        if ($this->hasTable($db, 'policies')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `policies` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `workspace_id` BIGINT UNSIGNED NULL,
                `name` VARCHAR(150) NOT NULL,
                `slug` VARCHAR(150) NOT NULL,
                `description` VARCHAR(255) NULL,
                `effect_id` BIGINT UNSIGNED NULL,
                `effect` VARCHAR(10) NOT NULL DEFAULT 'allow',
                `subject_type` VARCHAR(60) NULL,
                `subject_id` BIGINT UNSIGNED NULL,
                `conditions` JSON NULL,
                `priority` INT NOT NULL DEFAULT 0,
                `is_system` TINYINT(1) NOT NULL DEFAULT 0,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `created_by` BIGINT UNSIGNED NULL,
                `updated_by` BIGINT UNSIGNED NULL,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                `deleted_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `policies_uuid_unique` (`uuid`),
                UNIQUE KEY `policies_workspace_slug_unique` (`workspace_id`, `slug`),
                KEY `policies_workspace_id_index` (`workspace_id`),
                KEY `policies_subject_index` (`subject_type`, `subject_id`),
                KEY `policies_workspace_active_index` (`workspace_id`, `is_active`),
                KEY `policies_effect_id_index` (`effect_id`),
                KEY `policies_created_by_index` (`created_by`),
                KEY `policies_updated_by_index` (`updated_by`),
                KEY `policies_deleted_at_index` (`deleted_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    /**
     * `policy_permissions` — pure pivot (composite PK, no surrogate id/uuid).
     */
    private function createPolicyPermissions(Database $db): void
    {
        if ($this->hasTable($db, 'policy_permissions')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `policy_permissions` (
                `policy_id` BIGINT UNSIGNED NOT NULL,
                `permission_id` BIGINT UNSIGNED NOT NULL,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `created_by` BIGINT UNSIGNED NULL,
                PRIMARY KEY (`policy_id`, `permission_id`),
                KEY `policy_permissions_permission_id_index` (`permission_id`),
                KEY `policy_permissions_created_by_index` (`created_by`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    /**
     * `permission_caches` — materialized effective permission set per principal.
     * No `uuid` (high-churn derived read model). Polymorphic principal_type/id
     * (no FK). One row per (principal, permission) + a JSON snapshot row.
     */
    private function createPermissionCaches(Database $db): void
    {
        if ($this->hasTable($db, 'permission_caches')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `permission_caches` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `workspace_id` BIGINT UNSIGNED NULL,
                `principal_type` VARCHAR(40) NOT NULL,
                `principal_id` BIGINT UNSIGNED NOT NULL,
                `permission_id` BIGINT UNSIGNED NULL,
                `permission_key` VARCHAR(120) NULL,
                `permissions` JSON NULL,
                `source` VARCHAR(40) NULL,
                `computed_at` TIMESTAMP NULL DEFAULT NULL,
                `expires_at` TIMESTAMP NULL DEFAULT NULL,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `permission_caches_principal_permission_unique` (`principal_type`, `principal_id`, `permission_id`),
                KEY `permission_caches_principal_index` (`principal_type`, `principal_id`),
                KEY `permission_caches_lookup_index` (`principal_type`, `principal_id`, `permission_key`),
                KEY `permission_caches_workspace_id_index` (`workspace_id`),
                KEY `permission_caches_permission_id_index` (`permission_id`),
                KEY `permission_caches_expires_at_index` (`expires_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    /**
     * `role_histories` — append-only role grant/revoke trail. Sparse `uuid`
     * (UNIQUE allows multiple NULLs). Polymorphic principal_type/id (no FK).
     */
    // scale: partition candidate by RANGE(created_at)
    private function createRoleHistories(Database $db): void
    {
        if ($this->hasTable($db, 'role_histories')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `role_histories` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NULL,
                `workspace_id` BIGINT UNSIGNED NULL,
                `role_id` BIGINT UNSIGNED NULL,
                `principal_type` VARCHAR(40) NOT NULL,
                `principal_id` BIGINT UNSIGNED NOT NULL,
                `action_id` BIGINT UNSIGNED NULL,
                `action` VARCHAR(20) NULL,
                `reason` VARCHAR(255) NULL,
                `performed_by` BIGINT UNSIGNED NULL,
                `meta` JSON NULL,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `role_histories_uuid_unique` (`uuid`),
                KEY `role_histories_principal_index` (`principal_type`, `principal_id`),
                KEY `role_histories_role_id_index` (`role_id`),
                KEY `role_histories_workspace_created_index` (`workspace_id`, `created_at`),
                KEY `role_histories_performed_by_index` (`performed_by`),
                KEY `role_histories_action_id_index` (`action_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    /**
     * `permission_histories` — append-only permission grant/revoke trail on
     * roles/policies. Sparse `uuid`. Polymorphic grantor_type/id (no FK).
     */
    // scale: partition candidate by RANGE(created_at)
    private function createPermissionHistories(Database $db): void
    {
        if ($this->hasTable($db, 'permission_histories')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `permission_histories` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NULL,
                `workspace_id` BIGINT UNSIGNED NULL,
                `permission_id` BIGINT UNSIGNED NULL,
                `permission_key` VARCHAR(120) NULL,
                `grantor_type` VARCHAR(40) NOT NULL,
                `grantor_id` BIGINT UNSIGNED NOT NULL,
                `action_id` BIGINT UNSIGNED NULL,
                `action` VARCHAR(20) NULL,
                `reason` VARCHAR(255) NULL,
                `performed_by` BIGINT UNSIGNED NULL,
                `meta` JSON NULL,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `permission_histories_uuid_unique` (`uuid`),
                KEY `permission_histories_grantor_index` (`grantor_type`, `grantor_id`),
                KEY `permission_histories_permission_id_index` (`permission_id`),
                KEY `permission_histories_workspace_created_index` (`workspace_id`, `created_at`),
                KEY `permission_histories_performed_by_index` (`performed_by`),
                KEY `permission_histories_action_id_index` (`action_id`)
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
