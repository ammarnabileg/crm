<?php

declare(strict_types=1);

use App\Core\Database;
use Database\Migration;

/**
 * Final cutover: eliminate every hard-coded status/type ENUM column on the BUILT
 * tables, replacing each with a configuration-driven FK (the platform's mandatory
 * "no ENUM" rule). Per-entity workflow statuses point at their status tables;
 * simple lists point at lookup_values:
 *
 *   workspaces.status    -> workspace_status_id    -> workspace_statuses   (0030)
 *   subscriptions.status -> subscription_status_id -> subscription_statuses(D4)
 *   memberships.status   -> membership_status_id   -> lookup_values[membership_status]
 *   users.status         -> user_status_id         -> lookup_values[user_status]
 *   plans.interval       -> interval_id            -> lookup_values[billing_interval]
 *
 * The three lookup categories this depends on are seeded here first (system scope)
 * so the backfill resolves. Each conversion: add nullable FK column, backfill from
 * the old string, enforce NOT NULL, index + FK (RESTRICT), drop the ENUM column.
 * Idempotent via information_schema guards.
 */
return new class extends Migration {
    public function up(Database $db): void
    {
        // Lookup categories + values needed by the membership/user/plan conversions.
        $this->seedLookup($db, 'membership_status', 'Membership Status', [
            ['active', 'Active', 1],
            ['invited', 'Invited', 0],
            ['suspended', 'Suspended', 0],
        ]);
        $this->seedLookup($db, 'user_status', 'User Status', [
            ['active', 'Active', 1],
            ['suspended', 'Suspended', 0],
            ['pending', 'Pending', 0],
        ]);
        $this->seedLookup($db, 'billing_interval', 'Billing Interval', [
            ['monthly', 'Monthly', 1],
            ['yearly', 'Yearly', 0],
        ]);

        // 1) workspaces.status -> workspace_status_id (per-entity status table).
        $this->convertToStatusTable($db, 'workspaces', 'status', 'workspace_status_id', 'workspace_statuses');

        // 2) subscriptions.status -> subscription_status_id (per-entity status table).
        $this->convertToStatusTable($db, 'subscriptions', 'status', 'subscription_status_id', 'subscription_statuses');

        // 3) memberships.status -> membership_status_id (lookup_values).
        $this->convertToLookup($db, 'memberships', 'status', 'membership_status_id', 'membership_status');

        // 4) users.status -> user_status_id (lookup_values).
        $this->convertToLookup($db, 'users', 'status', 'user_status_id', 'user_status');

        // 5) plans.interval -> interval_id (lookup_values).
        $this->convertToLookup($db, 'plans', 'interval', 'interval_id', 'billing_interval');
    }

    public function down(Database $db): void
    {
        // Best-effort reverse: re-add the ENUM columns and backfill from the FK.
        $this->revertToEnum($db, 'workspaces', 'status', 'workspace_status_id', "ENUM('trial','active','suspended','canceled')", 'trial', 'workspace_statuses');
        $this->revertToEnum($db, 'subscriptions', 'status', 'subscription_status_id', "ENUM('trialing','active','past_due','canceled','expired')", 'trialing', 'subscription_statuses');
        $this->revertLookupToEnum($db, 'memberships', 'status', 'membership_status_id', "ENUM('active','invited','suspended')", 'active');
        $this->revertLookupToEnum($db, 'users', 'status', 'user_status_id', "ENUM('active','suspended','pending')", 'active');
        $this->revertLookupToEnum($db, 'plans', 'interval', 'interval_id', "ENUM('monthly','yearly')", 'monthly');
    }

    /**
     * Convert an ENUM string column to a FK at a per-entity status table.
     */
    private function convertToStatusTable(Database $db, string $table, string $old, string $new, string $statusTable): void
    {
        if (! $this->hasColumn($db, $table, $old) || $this->hasColumn($db, $table, $new)) {
            return;
        }

        $db->unprepared("ALTER TABLE `{$table}` ADD COLUMN `{$new}` BIGINT UNSIGNED NULL AFTER `{$old}`");
        $db->unprepared(
            "UPDATE `{$table}` t JOIN `{$statusTable}` s ON s.`key` = t.`{$old}` AND s.workspace_id IS NULL
             SET t.`{$new}` = s.id"
        );
        $this->finishColumn($db, $table, $old, $new, "`{$statusTable}`");
    }

    /**
     * Convert an ENUM string column to a FK at lookup_values (by category).
     */
    private function convertToLookup(Database $db, string $table, string $old, string $new, string $category): void
    {
        if (! $this->hasColumn($db, $table, $old) || $this->hasColumn($db, $table, $new)) {
            return;
        }

        $db->unprepared("ALTER TABLE `{$table}` ADD COLUMN `{$new}` BIGINT UNSIGNED NULL AFTER `{$old}`");
        // Parameterised (unprepared() takes no bindings) so the category key is bound safely.
        $db->affectingStatement(
            "UPDATE `{$table}` t
             JOIN lookup_values lv ON lv.`key` = t.`{$old}` AND lv.workspace_id IS NULL
             JOIN lookup_categories lc ON lc.id = lv.category_id AND lc.`key` = ? AND lc.workspace_id IS NULL
             SET t.`{$new}` = lv.id",
            [$category]
        );
        $this->finishColumn($db, $table, $old, $new, 'lookup_values');
    }

    /**
     * Enforce NOT NULL, add index + RESTRICT FK, drop the old ENUM column.
     */
    private function finishColumn(Database $db, string $table, string $old, string $new, string $ref): void
    {
        $orphans = (int) $db->scalar("SELECT COUNT(*) FROM `{$table}` WHERE `{$new}` IS NULL");
        if ($orphans > 0) {
            throw new \RuntimeException("0032: {$orphans} row(s) in {$table}.{$new} could not be mapped from {$old}.");
        }

        $db->unprepared("ALTER TABLE `{$table}` MODIFY `{$new}` BIGINT UNSIGNED NOT NULL");
        $db->unprepared("ALTER TABLE `{$table}` ADD KEY `{$table}_{$new}_index` (`{$new}`)");
        $db->unprepared(
            "ALTER TABLE `{$table}` ADD CONSTRAINT `{$table}_{$new}_foreign`
             FOREIGN KEY (`{$new}`) REFERENCES {$ref} (`id`) ON DELETE RESTRICT ON UPDATE CASCADE"
        );
        $db->unprepared("ALTER TABLE `{$table}` DROP COLUMN `{$old}`");
    }

    private function revertToEnum(Database $db, string $table, string $old, string $new, string $enumDef, string $default, string $statusTable): void
    {
        if (! $this->hasColumn($db, $table, $new) || $this->hasColumn($db, $table, $old)) {
            return;
        }
        if ($this->hasConstraint($db, $table, "{$table}_{$new}_foreign")) {
            $db->unprepared("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$table}_{$new}_foreign`");
        }
        $db->unprepared("ALTER TABLE `{$table}` ADD COLUMN `{$old}` {$enumDef} NOT NULL DEFAULT '{$default}' AFTER `{$new}`");
        $db->unprepared(
            "UPDATE `{$table}` t JOIN `{$statusTable}` s ON s.id = t.`{$new}` SET t.`{$old}` = s.`key`"
        );
        $db->unprepared("ALTER TABLE `{$table}` DROP COLUMN `{$new}`");
    }

    private function revertLookupToEnum(Database $db, string $table, string $old, string $new, string $enumDef, string $default): void
    {
        if (! $this->hasColumn($db, $table, $new) || $this->hasColumn($db, $table, $old)) {
            return;
        }
        if ($this->hasConstraint($db, $table, "{$table}_{$new}_foreign")) {
            $db->unprepared("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$table}_{$new}_foreign`");
        }
        $db->unprepared("ALTER TABLE `{$table}` ADD COLUMN `{$old}` {$enumDef} NOT NULL DEFAULT '{$default}' AFTER `{$new}`");
        $db->unprepared(
            "UPDATE `{$table}` t JOIN lookup_values lv ON lv.id = t.`{$new}` SET t.`{$old}` = lv.`key`"
        );
        $db->unprepared("ALTER TABLE `{$table}` DROP COLUMN `{$new}`");
    }

    /**
     * Seed a system-scope lookup category and its values (idempotent).
     *
     * @param array<int, array{0:string,1:string,2:int}> $values [key, label, is_default]
     */
    private function seedLookup(Database $db, string $categoryKey, string $categoryLabel, array $values): void
    {
        $catId = $db->table('lookup_categories')->whereNull('workspace_id')->where('key', '=', $categoryKey)->value('id');
        $now = now();
        if ($catId === null) {
            $catId = $db->table('lookup_categories')->insertGetId([
                'uuid'       => $this->uuid($db),
                'workspace_id' => null,
                'key'        => $categoryKey,
                'label'      => $categoryLabel,
                'is_system'  => 1,
                'is_active'  => 1,
                'sort_order' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        $catId = (int) $catId;

        $order = 0;
        foreach ($values as [$key, $label, $isDefault]) {
            $order++;
            $exists = $db->table('lookup_values')
                ->where('category_id', '=', $catId)
                ->whereNull('workspace_id')
                ->where('key', '=', $key)
                ->exists();
            if ($exists) {
                continue;
            }
            $db->table('lookup_values')->insert([
                'uuid'        => $this->uuid($db),
                'category_id' => $catId,
                'workspace_id' => null,
                'key'         => $key,
                'label'       => $label,
                'sort_order'  => $order,
                'is_default'  => $isDefault,
                'is_system'   => 1,
                'is_active'   => 1,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
        }
    }

    private function uuid(Database $db): string
    {
        return (string) $db->scalar('SELECT UUID()');
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
};
