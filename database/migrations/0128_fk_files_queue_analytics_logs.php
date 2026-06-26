<?php

declare(strict_types=1);

use App\Core\Database;
use Database\Migration;

/**
 * D10 — Files, Queue, Analytics & Logs (FOREIGN KEYS only).
 *
 * Paired FK migration for 0028_create_files_queue_analytics_logs.php. Every table
 * in the domain is created first (0028); this file adds the relational
 * constraints last so create-order / cross-domain cycles never break the build.
 *
 * FKs live ONLY on the File Manager tables (storage_providers, folders, files,
 * file_versions). Everything else in the domain is deliberately FK-LIGHT by the
 * spec (Bible §1/§4/§7) and gets NO foreign keys here:
 *  - Queue/Scheduler (queued_jobs, failed_jobs, scheduled_tasks): high-churn /
 *    config; workspace_id is an indexed soft reference only.
 *  - Analytics rollups (daily/monthly/usage/hiring/ai/interview/performance):
 *    derived, partition-ready; workspace_id + dimensions are soft refs.
 *  - Logs (system/security/api/billing): append-only, partition-ready; subjects
 *    are polymorphic, all references soft. The BUILT `activity_log` keeps its own
 *    two FKs (added in 0015/0017) and is not touched here.
 *
 * Cross-domain / BUILT-table FK targets: `workspaces`, `users` (BUILT cores) and
 * `lookup_values` (from migration 0018). Intra-domain targets: `folders`,
 * `files`, `storage_providers`. All referenced PKs are `id` BIGINT UNSIGNED.
 * Each ALTER is guarded by hasConstraint (idempotent).
 */
return new class extends Migration {
    /**
     * FK definitions: [table, constraint, column, ref_table, ref_col, on_delete, on_update].
     * Only File Manager tables carry hard FKs (the rest of D10 is FK-light by spec).
     */
    private array $foreignKeys = [
        // storage_providers
        ['storage_providers', 'storage_providers_workspace_id_foreign', 'workspace_id', 'workspaces', 'id', 'CASCADE', 'CASCADE'],

        // folders (self-referential parent_id; created_by → users)
        ['folders', 'folders_workspace_id_foreign', 'workspace_id', 'workspaces', 'id', 'CASCADE', 'CASCADE'],
        ['folders', 'folders_parent_id_foreign', 'parent_id', 'folders', 'id', 'CASCADE', 'CASCADE'],
        ['folders', 'folders_created_by_foreign', 'created_by', 'users', 'id', 'SET NULL', 'CASCADE'],

        // files
        ['files', 'files_workspace_id_foreign', 'workspace_id', 'workspaces', 'id', 'CASCADE', 'CASCADE'],
        ['files', 'files_user_id_foreign', 'user_id', 'users', 'id', 'SET NULL', 'CASCADE'],
        ['files', 'files_folder_id_foreign', 'folder_id', 'folders', 'id', 'SET NULL', 'CASCADE'],
        ['files', 'files_storage_provider_id_foreign', 'storage_provider_id', 'storage_providers', 'id', 'RESTRICT', 'CASCADE'],
        ['files', 'files_visibility_id_foreign', 'visibility_id', 'lookup_values', 'id', 'RESTRICT', 'CASCADE'],

        // file_versions
        ['file_versions', 'file_versions_file_id_foreign', 'file_id', 'files', 'id', 'CASCADE', 'CASCADE'],
        ['file_versions', 'file_versions_workspace_id_foreign', 'workspace_id', 'workspaces', 'id', 'CASCADE', 'CASCADE'],
        ['file_versions', 'file_versions_created_by_foreign', 'created_by', 'users', 'id', 'SET NULL', 'CASCADE'],
    ];

    public function up(Database $db): void
    {
        foreach ($this->foreignKeys as [$table, $constraint, $column, $refTable, $refCol, $onDelete, $onUpdate]) {
            if (! $this->hasTable($db, $table) || ! $this->hasTable($db, $refTable)) {
                continue;
            }
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
        // Drop in reverse so dependents go before their targets.
        foreach (array_reverse($this->foreignKeys) as [$table, $constraint]) {
            if ($this->hasConstraint($db, $table, $constraint)) {
                $db->unprepared("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$constraint}`");
            }
        }
    }

    // --------------------------------------------------------------- Helpers

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
