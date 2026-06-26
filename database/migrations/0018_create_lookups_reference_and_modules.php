<?php

declare(strict_types=1);

use App\Core\Database;
use Database\Migration;

/**
 * D0 foundation + Revision R1 registries (approved blueprint).
 *
 * Creates the shared foundation every other domain depends on:
 *  - Global reference data: `currencies`, `countries`, `languages`, `timezones`.
 *  - Configuration-driven lookups: `lookup_categories` + `lookup_values`
 *    (the no-ENUM mechanism for simple configurable lists — docs/database/01).
 *  - The module registry: `system_modules`, and the R1 link
 *    `permissions.module_id` (the module IS the permission group) which
 *    supersedes the free-text `permissions.group` (docs/database/12 R1-03).
 *
 * Bulk reference rows (the world's currencies/countries/languages/timezones) and
 * lookup VALUES are seeded by database/seeders/DatabaseSeeder.php (idempotent);
 * this migration owns STRUCTURE plus the small `system_modules` catalog that the
 * permission backfill below depends on. Idempotent via information_schema guards.
 */
return new class extends Migration {
    /** The functional module registry (docs/database/12). The module IS the permission group. */
    private array $modules = [
        // [key, label, group, icon, sort]
        ['dashboard', 'Dashboard', 'Platform', 'layout-dashboard', 1],
        ['workspaces', 'Workspaces', 'Platform', 'building', 2],
        ['members', 'Members', 'Platform', 'users', 3],
        ['users', 'Users', 'Platform', 'user', 4],
        ['roles', 'Roles', 'Platform', 'shield', 5],
        ['permissions', 'Permissions', 'Platform', 'key', 6],
        ['settings', 'Settings', 'Platform', 'settings', 7],
        ['integrations', 'Integrations', 'Platform', 'plug', 8],
        ['files', 'Files', 'Platform', 'folder', 9],
        ['notifications', 'Notifications', 'Platform', 'bell', 10],
        ['jobs', 'Jobs', 'Hiring', 'briefcase', 11],
        ['candidates', 'Candidates', 'Hiring', 'id-card', 12],
        ['applications', 'Applications', 'Hiring', 'file-text', 13],
        ['interviews', 'Interviews', 'Hiring', 'video', 14],
        ['offers', 'Offers', 'Hiring', 'handshake', 15],
        ['talent_pool', 'Talent Pool', 'Hiring', 'users-round', 16],
        ['ai', 'AI', 'Intelligence', 'sparkles', 17],
        ['reports', 'Reports', 'Intelligence', 'bar-chart', 18],
        ['analytics', 'Analytics', 'Intelligence', 'line-chart', 19],
        ['billing', 'Billing', 'Billing', 'credit-card', 20],
        ['subscriptions', 'Subscriptions', 'Billing', 'repeat', 21],
    ];

    public function up(Database $db): void
    {
        $this->createCurrencies($db);
        $this->createCountries($db);
        $this->createLanguages($db);
        $this->createTimezones($db);
        $this->createLookupCategories($db);
        $this->createLookupValues($db);
        $this->createSystemModules($db);
        $this->linkPermissionsToModules($db);
    }

    public function down(Database $db): void
    {
        // Restore permissions.group / drop the module link.
        if ($this->hasColumn($db, 'permissions', 'module_id')) {
            if ($this->hasConstraint($db, 'permissions', 'permissions_module_id_foreign')) {
                $db->unprepared('ALTER TABLE `permissions` DROP FOREIGN KEY `permissions_module_id_foreign`');
            }
            if ($this->hasIndex($db, 'permissions', 'permissions_module_action_unique')) {
                $db->unprepared('ALTER TABLE `permissions` DROP INDEX `permissions_module_action_unique`');
            }
            if ($this->hasIndex($db, 'permissions', 'permissions_module_id_index')) {
                $db->unprepared('ALTER TABLE `permissions` DROP INDEX `permissions_module_id_index`');
            }
            $db->unprepared('ALTER TABLE `permissions` DROP COLUMN `module_id`');
        }
        if ($this->hasColumn($db, 'permissions', 'action')) {
            $db->unprepared('ALTER TABLE `permissions` DROP COLUMN `action`');
        }
        if (! $this->hasColumn($db, 'permissions', 'group')) {
            $db->unprepared("ALTER TABLE `permissions` ADD COLUMN `group` VARCHAR(80) NOT NULL DEFAULT 'General' AFTER `name`");
            $db->unprepared('ALTER TABLE `permissions` ADD KEY `permissions_group_index` (`group`)');
        }

        foreach (['system_modules', 'lookup_values', 'lookup_categories', 'timezones', 'countries', 'languages', 'currencies'] as $table) {
            $db->unprepared("DROP TABLE IF EXISTS `{$table}`");
        }
    }

    private function createCurrencies(Database $db): void
    {
        if ($this->hasTable($db, 'currencies')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `currencies` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `code` CHAR(3) NOT NULL,
                `numeric_code` CHAR(3) NULL,
                `name` VARCHAR(80) NOT NULL,
                `symbol` VARCHAR(8) NULL,
                `decimal_places` TINYINT UNSIGNED NOT NULL DEFAULT 2,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `sort_order` INT NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `currencies_uuid_unique` (`uuid`),
                UNIQUE KEY `currencies_code_unique` (`code`),
                KEY `currencies_is_active_index` (`is_active`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    private function createCountries(Database $db): void
    {
        if ($this->hasTable($db, 'countries')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `countries` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `iso2` CHAR(2) NOT NULL,
                `iso3` CHAR(3) NOT NULL,
                `numeric_code` CHAR(3) NULL,
                `name` VARCHAR(120) NOT NULL,
                `native_name` VARCHAR(120) NULL,
                `phone_code` VARCHAR(8) NULL,
                `default_currency_id` BIGINT UNSIGNED NULL,
                `region` VARCHAR(60) NULL,
                `flag_emoji` VARCHAR(16) NULL,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `sort_order` INT NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `countries_uuid_unique` (`uuid`),
                UNIQUE KEY `countries_iso2_unique` (`iso2`),
                UNIQUE KEY `countries_iso3_unique` (`iso3`),
                KEY `countries_default_currency_id_index` (`default_currency_id`),
                KEY `countries_name_index` (`name`),
                CONSTRAINT `countries_default_currency_id_foreign` FOREIGN KEY (`default_currency_id`)
                    REFERENCES `currencies` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    private function createLanguages(Database $db): void
    {
        if ($this->hasTable($db, 'languages')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `languages` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `code` VARCHAR(10) NOT NULL,
                `name` VARCHAR(80) NOT NULL,
                `native_name` VARCHAR(80) NULL,
                `direction` CHAR(3) NOT NULL DEFAULT 'ltr',
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `is_default` TINYINT(1) NOT NULL DEFAULT 0,
                `sort_order` INT NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `languages_uuid_unique` (`uuid`),
                UNIQUE KEY `languages_code_unique` (`code`),
                KEY `languages_is_active_index` (`is_active`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    private function createTimezones(Database $db): void
    {
        if ($this->hasTable($db, 'timezones')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `timezones` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `name` VARCHAR(64) NOT NULL,
                `abbreviation` VARCHAR(12) NULL,
                `utc_offset` VARCHAR(9) NULL,
                `offset_minutes` SMALLINT NULL,
                `country_id` BIGINT UNSIGNED NULL,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `sort_order` INT NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `timezones_uuid_unique` (`uuid`),
                UNIQUE KEY `timezones_name_unique` (`name`),
                KEY `timezones_country_id_index` (`country_id`),
                KEY `timezones_is_active_index` (`is_active`),
                CONSTRAINT `timezones_country_id_foreign` FOREIGN KEY (`country_id`)
                    REFERENCES `countries` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    private function createLookupCategories(Database $db): void
    {
        if ($this->hasTable($db, 'lookup_categories')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `lookup_categories` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `workspace_id` BIGINT UNSIGNED NULL,
                `key` VARCHAR(60) NOT NULL,
                `label` VARCHAR(120) NOT NULL,
                `description` VARCHAR(255) NULL,
                `is_system` TINYINT(1) NOT NULL DEFAULT 0,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `sort_order` INT NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                `deleted_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `lookup_categories_uuid_unique` (`uuid`),
                UNIQUE KEY `lookup_categories_workspace_key_unique` (`workspace_id`, `key`),
                KEY `lookup_categories_workspace_id_index` (`workspace_id`),
                KEY `lookup_categories_key_index` (`key`),
                KEY `lookup_categories_deleted_at_index` (`deleted_at`),
                CONSTRAINT `lookup_categories_workspace_id_foreign` FOREIGN KEY (`workspace_id`)
                    REFERENCES `workspaces` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    private function createLookupValues(Database $db): void
    {
        if ($this->hasTable($db, 'lookup_values')) {
            return;
        }
        $db->unprepared(
            "CREATE TABLE `lookup_values` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `uuid` CHAR(36) NOT NULL,
                `category_id` BIGINT UNSIGNED NOT NULL,
                `workspace_id` BIGINT UNSIGNED NULL,
                `parent_id` BIGINT UNSIGNED NULL,
                `key` VARCHAR(60) NOT NULL,
                `label` VARCHAR(120) NOT NULL,
                `meta` JSON NULL,
                `sort_order` INT NOT NULL DEFAULT 0,
                `is_default` TINYINT(1) NOT NULL DEFAULT 0,
                `is_system` TINYINT(1) NOT NULL DEFAULT 0,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                `deleted_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `lookup_values_uuid_unique` (`uuid`),
                UNIQUE KEY `lookup_values_category_workspace_key_unique` (`category_id`, `workspace_id`, `key`),
                KEY `lookup_values_category_id_index` (`category_id`),
                KEY `lookup_values_workspace_id_index` (`workspace_id`),
                KEY `lookup_values_parent_id_index` (`parent_id`),
                KEY `lookup_values_category_active_sort_index` (`category_id`, `is_active`, `sort_order`),
                KEY `lookup_values_deleted_at_index` (`deleted_at`),
                CONSTRAINT `lookup_values_category_id_foreign` FOREIGN KEY (`category_id`)
                    REFERENCES `lookup_categories` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `lookup_values_workspace_id_foreign` FOREIGN KEY (`workspace_id`)
                    REFERENCES `workspaces` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `lookup_values_parent_id_foreign` FOREIGN KEY (`parent_id`)
                    REFERENCES `lookup_values` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    private function createSystemModules(Database $db): void
    {
        if (! $this->hasTable($db, 'system_modules')) {
            $db->unprepared(
                "CREATE TABLE `system_modules` (
                    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `uuid` CHAR(36) NOT NULL,
                    `key` VARCHAR(60) NOT NULL,
                    `label` VARCHAR(120) NOT NULL,
                    `description` VARCHAR(255) NULL,
                    `icon` VARCHAR(60) NULL,
                    `group` VARCHAR(60) NULL,
                    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                    `sort_order` INT NOT NULL DEFAULT 0,
                    `created_at` TIMESTAMP NULL DEFAULT NULL,
                    `updated_at` TIMESTAMP NULL DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `system_modules_uuid_unique` (`uuid`),
                    UNIQUE KEY `system_modules_key_unique` (`key`),
                    KEY `system_modules_active_index` (`is_active`, `sort_order`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }

        $now = date('Y-m-d H:i:s');
        foreach ($this->modules as [$key, $label, $group, $icon, $sort]) {
            if ($db->table('system_modules')->where('key', '=', $key)->exists()) {
                continue;
            }
            $db->table('system_modules')->insert([
                'uuid'       => $this->uuid($db),
                'key'        => $key,
                'label'      => $label,
                'description' => $label . ' module.',
                'icon'       => $icon,
                'group'      => $group,
                'is_active'  => 1,
                'sort_order' => $sort,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * R1-03: bind each permission to a module + action and drop the free-text
     * `group`. The canonical key stays `<module>.<action>`; storage references the
     * module by id so renaming a module never breaks grants.
     */
    private function linkPermissionsToModules(Database $db): void
    {
        if (! $this->hasColumn($db, 'permissions', 'module_id')) {
            $db->unprepared('ALTER TABLE `permissions` ADD COLUMN `module_id` BIGINT UNSIGNED NULL AFTER `key`');
            $db->unprepared('ALTER TABLE `permissions` ADD COLUMN `action` VARCHAR(60) NULL AFTER `module_id`');

            // Backfill action = segment after the last dot; module = segment
            // before the first dot (aliasing the singular `workspace` to the
            // `workspaces` module).
            $db->unprepared("UPDATE `permissions` SET `action` = SUBSTRING_INDEX(`key`, '.', -1) WHERE `action` IS NULL");
            $db->unprepared(
                "UPDATE `permissions` p
                 SET p.`module_id` = (
                     SELECT m.`id` FROM `system_modules` m
                     WHERE m.`key` = CASE SUBSTRING_INDEX(p.`key`, '.', 1)
                         WHEN 'workspace' THEN 'workspaces'
                         ELSE SUBSTRING_INDEX(p.`key`, '.', 1)
                     END
                 )
                 WHERE p.`module_id` IS NULL"
            );

            // Any permission whose module is unknown would block the NOT NULL
            // change — fail loudly rather than silently drop the grant.
            $orphans = (int) $db->scalar('SELECT COUNT(*) FROM `permissions` WHERE `module_id` IS NULL');
            if ($orphans > 0) {
                throw new \RuntimeException("0018: {$orphans} permission(s) could not be mapped to a system module.");
            }

            $db->unprepared('ALTER TABLE `permissions` MODIFY `module_id` BIGINT UNSIGNED NOT NULL');
            $db->unprepared('ALTER TABLE `permissions` MODIFY `action` VARCHAR(60) NOT NULL');
            $db->unprepared('ALTER TABLE `permissions` ADD KEY `permissions_module_id_index` (`module_id`)');
            $db->unprepared('ALTER TABLE `permissions` ADD UNIQUE KEY `permissions_module_action_unique` (`module_id`, `action`)');
            $db->unprepared(
                'ALTER TABLE `permissions` ADD CONSTRAINT `permissions_module_id_foreign`
                 FOREIGN KEY (`module_id`) REFERENCES `system_modules` (`id`)
                 ON DELETE RESTRICT ON UPDATE CASCADE'
            );
        }

        // Drop the superseded free-text group (and its index).
        if ($this->hasColumn($db, 'permissions', 'group')) {
            if ($this->hasIndex($db, 'permissions', 'permissions_group_index')) {
                $db->unprepared('ALTER TABLE `permissions` DROP INDEX `permissions_group_index`');
            }
            $db->unprepared('ALTER TABLE `permissions` DROP COLUMN `group`');
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
