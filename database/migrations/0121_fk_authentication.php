<?php

declare(strict_types=1);

use App\Core\Database;
use Database\Migration;

/**
 * D3 — Authentication: FOREIGN KEYS (companion to 0021_create_authentication.php).
 *
 * Adds every FK for the Authentication domain AFTER all tables exist, so
 * create-order and cross-domain cycles never break the build. Each ADD/DROP is
 * guarded by an information_schema check (idempotent).
 *
 * FK targets:
 *  - Intra-domain : `devices`, `mfa_methods`.
 *  - BUILT tables : `users` (0001), `workspaces` (0017, ex-companies).
 *  - D0 (0018)    : `lookup_values` (device_type / mfa_method_type /
 *                   login_failure_reason), `countries`.
 *
 * `failed_login_attempts` has NO foreign keys by design (keyed by email/ip, not
 * user_id — throttle non-existent/locked accounts without leaking existence).
 *
 * On-delete/update semantics follow the per-table "Foreign keys" tables in
 * docs/database/04-Authentication.md exactly.
 */
return new class extends Migration {
    /**
     * Each row: [table, constraint, column, ref_table, ref_col, on_delete, on_update].
     */
    private array $foreignKeys = [
        // 1. sessions
        ['sessions', 'sessions_user_id_foreign', 'user_id', 'users', 'id', 'CASCADE', 'CASCADE'],
        ['sessions', 'sessions_device_id_foreign', 'device_id', 'devices', 'id', 'SET NULL', 'CASCADE'],

        // 2. remember_tokens
        ['remember_tokens', 'remember_tokens_user_id_foreign', 'user_id', 'users', 'id', 'CASCADE', 'CASCADE'],
        ['remember_tokens', 'remember_tokens_device_id_foreign', 'device_id', 'devices', 'id', 'SET NULL', 'CASCADE'],

        // 4. login_histories
        ['login_histories', 'login_histories_user_id_foreign', 'user_id', 'users', 'id', 'SET NULL', 'CASCADE'],
        ['login_histories', 'login_histories_device_id_foreign', 'device_id', 'devices', 'id', 'SET NULL', 'CASCADE'],
        ['login_histories', 'login_histories_failure_reason_id_foreign', 'failure_reason_id', 'lookup_values', 'id', 'RESTRICT', 'CASCADE'],
        ['login_histories', 'login_histories_country_id_foreign', 'country_id', 'countries', 'id', 'SET NULL', 'CASCADE'],

        // 5. devices
        ['devices', 'devices_user_id_foreign', 'user_id', 'users', 'id', 'CASCADE', 'CASCADE'],
        ['devices', 'devices_type_id_foreign', 'type_id', 'lookup_values', 'id', 'RESTRICT', 'CASCADE'],

        // 7. mfa_methods
        ['mfa_methods', 'mfa_methods_user_id_foreign', 'user_id', 'users', 'id', 'CASCADE', 'CASCADE'],
        ['mfa_methods', 'mfa_methods_type_id_foreign', 'type_id', 'lookup_values', 'id', 'RESTRICT', 'CASCADE'],

        // 8. mfa_recovery_codes
        ['mfa_recovery_codes', 'mfa_recovery_codes_user_id_foreign', 'user_id', 'users', 'id', 'CASCADE', 'CASCADE'],
        ['mfa_recovery_codes', 'mfa_recovery_codes_mfa_method_id_foreign', 'mfa_method_id', 'mfa_methods', 'id', 'CASCADE', 'CASCADE'],

        // 9. personal_access_tokens
        ['personal_access_tokens', 'personal_access_tokens_user_id_foreign', 'user_id', 'users', 'id', 'CASCADE', 'CASCADE'],
        ['personal_access_tokens', 'personal_access_tokens_workspace_id_foreign', 'workspace_id', 'workspaces', 'id', 'CASCADE', 'CASCADE'],
    ];

    public function up(Database $db): void
    {
        foreach ($this->foreignKeys as [$table, $constraint, $column, $refTable, $refCol, $onDelete, $onUpdate]) {
            if ($this->hasConstraint($db, $table, $constraint)) {
                continue;
            }
            $db->unprepared(
                "ALTER TABLE `{$table}` ADD CONSTRAINT `{$constraint}` "
                . "FOREIGN KEY (`{$column}`) REFERENCES `{$refTable}` (`{$refCol}`) "
                . "ON DELETE {$onDelete} ON UPDATE {$onUpdate}"
            );
        }
    }

    public function down(Database $db): void
    {
        foreach ($this->foreignKeys as [$table, $constraint]) {
            if ($this->hasConstraint($db, $table, $constraint)) {
                $db->unprepared("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$constraint}`");
            }
        }
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
