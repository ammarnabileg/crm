<?php

declare(strict_types=1);

use App\Core\Database;
use Database\Migration;

/**
 * Foreign keys for the D0 polymorphic shared tables (created in 0029). Only the
 * GENUINE relationships are FKs — the morph pairs (`*_type`,`*_id`) and the
 * status ids in `status_histories` carry NO FK by design (they point at many
 * tables; integrity is app-enforced — docs/database/01). Runs after every
 * referenced table exists (incl. D10 `files`). Idempotent via hasConstraint.
 */
return new class extends Migration {
    /** table => [ [column, ref_table, on_delete], ... ] (on update always CASCADE). */
    private array $fks = [
        'translations' => [
            ['workspace_id', 'workspaces', 'CASCADE'],
        ],
        'attachments' => [
            ['workspace_id', 'workspaces', 'CASCADE'],
            ['file_id', 'files', 'CASCADE'],
            ['uploaded_by', 'users', 'SET NULL'],
        ],
        'notes' => [
            ['workspace_id', 'workspaces', 'CASCADE'],
            ['type_id', 'lookup_values', 'RESTRICT'],
            ['user_id', 'users', 'SET NULL'],
        ],
        'tags' => [
            ['workspace_id', 'workspaces', 'CASCADE'],
            ['created_by', 'users', 'SET NULL'],
        ],
        'taggables' => [
            ['workspace_id', 'workspaces', 'CASCADE'],
            ['tag_id', 'tags', 'CASCADE'],
            ['tagged_by', 'users', 'SET NULL'],
        ],
        'status_histories' => [
            ['workspace_id', 'workspaces', 'CASCADE'],
            ['changed_by', 'users', 'SET NULL'],
        ],
    ];

    public function up(Database $db): void
    {
        foreach ($this->fks as $table => $list) {
            foreach ($list as [$column, $ref, $onDelete]) {
                $name = "{$table}_{$column}_foreign";
                if ($this->hasConstraint($db, $table, $name)) {
                    continue;
                }
                $db->unprepared(
                    "ALTER TABLE `{$table}` ADD CONSTRAINT `{$name}`
                     FOREIGN KEY (`{$column}`) REFERENCES `{$ref}` (`id`)
                     ON DELETE {$onDelete} ON UPDATE CASCADE"
                );
            }
        }
    }

    public function down(Database $db): void
    {
        foreach ($this->fks as $table => $list) {
            foreach ($list as [$column, $ref, $onDelete]) {
                $name = "{$table}_{$column}_foreign";
                if ($this->hasConstraint($db, $table, $name)) {
                    $db->unprepared("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$name}`");
                }
            }
        }
    }

    private function hasConstraint(Database $db, string $table, string $constraint): bool
    {
        return (int) $db->scalar(
            'SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?',
            [$table, $constraint]
        ) > 0;
    }
};
