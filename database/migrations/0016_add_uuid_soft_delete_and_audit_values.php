<?php

declare(strict_types=1);

use App\Core\Database;
use Database\Migration;

/**
 * Enterprise foundation (docs/47 EAS-7/EAS-8):
 *  - Add a public `uuid` to core entities (used in URLs/API instead of the
 *    numeric id) and back-fill existing rows.
 *  - Add `deleted_at` for soft deletes on core entities (no hard loss of
 *    important data).
 *  - Extend `activity_log` with `old_values`, `new_values`, and `device` so the
 *    audit trail records before/after state and the client device.
 *
 * Additive and backwards-compatible: existing queries keep working; models opt
 * into soft-delete behavior via their $softDeletes flag.
 */
return new class extends Migration {
    /** @var string[] Core entities that gain uuid + deleted_at. */
    private array $tables = [
        'users', 'companies', 'memberships', 'roles',
        'plans', 'subscriptions', 'ai_credentials',
    ];

    public function up(Database $db): void
    {
        foreach ($this->tables as $table) {
            if (! $this->hasColumn($db, $table, 'uuid')) {
                $db->unprepared("ALTER TABLE `{$table}` ADD COLUMN `uuid` CHAR(36) NULL AFTER `id`");
                $db->unprepared("UPDATE `{$table}` SET `uuid` = UUID() WHERE `uuid` IS NULL");
                $db->unprepared("ALTER TABLE `{$table}` ADD UNIQUE KEY `{$table}_uuid_unique` (`uuid`)");
            }
            if (! $this->hasColumn($db, $table, 'deleted_at')) {
                $db->unprepared("ALTER TABLE `{$table}` ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL");
                $db->unprepared("ALTER TABLE `{$table}` ADD KEY `{$table}_deleted_at_index` (`deleted_at`)");
            }
        }

        if (! $this->hasColumn($db, 'activity_log', 'old_values')) {
            $db->unprepared("ALTER TABLE `activity_log` ADD COLUMN `old_values` JSON NULL AFTER `description`");
        }
        if (! $this->hasColumn($db, 'activity_log', 'new_values')) {
            $db->unprepared("ALTER TABLE `activity_log` ADD COLUMN `new_values` JSON NULL AFTER `old_values`");
        }
        if (! $this->hasColumn($db, 'activity_log', 'device')) {
            $db->unprepared("ALTER TABLE `activity_log` ADD COLUMN `device` VARCHAR(255) NULL AFTER `user_agent`");
        }
    }

    public function down(Database $db): void
    {
        foreach ($this->tables as $table) {
            if ($this->hasColumn($db, $table, 'deleted_at')) {
                $db->unprepared("ALTER TABLE `{$table}` DROP KEY `{$table}_deleted_at_index`");
                $db->unprepared("ALTER TABLE `{$table}` DROP COLUMN `deleted_at`");
            }
            if ($this->hasColumn($db, $table, 'uuid')) {
                $db->unprepared("ALTER TABLE `{$table}` DROP KEY `{$table}_uuid_unique`");
                $db->unprepared("ALTER TABLE `{$table}` DROP COLUMN `uuid`");
            }
        }

        foreach (['old_values', 'new_values', 'device'] as $column) {
            if ($this->hasColumn($db, 'activity_log', $column)) {
                $db->unprepared("ALTER TABLE `activity_log` DROP COLUMN `{$column}`");
            }
        }
    }

    private function hasColumn(Database $db, string $table, string $column): bool
    {
        $row = $db->selectOne(
            'SELECT COUNT(*) AS c FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        );

        return (int) ($row['c'] ?? 0) > 0;
    }
};
